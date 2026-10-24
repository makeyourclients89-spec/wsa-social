<?php
declare(strict_types=1);

// ----------------------------------------------------------------------
// WSA Multi-Channel Publisher - Central Queue Publishing Engine
// ----------------------------------------------------------------------

class QueueEngine {
    /**
     * Dispatches due queued automated publishing jobs.
     * Can be invoked from both Web UI and CLI worker.
     *
     * @param int $limit Maximum jobs to process per run
     * @param callable|null $logger Optional output callback function (string $msg, string $type)
     * @return array [published => int, failed => int]
     */
    public static function process(int $limit = 50, ?callable $logger = null): array {
        $pdo = db();
        $now = now();

        // Update runner heartbeat timestamp
        set_system_setting('runner_last_heartbeat', $now);

        $stmt = $pdo->prepare("
            SELECT j.*, c.name AS campaign_name, c.message, c.link_url, c.media_path, c.auto_boost, cl.name AS client_name
            FROM jobs j
            JOIN campaigns c ON c.id = j.campaign_id
            JOIN clients cl ON cl.id = j.client_id
            WHERE j.status = 'queued'
              AND j.execution_mode = 'automated'
              AND j.scheduled_for <= datetime('now', 'localtime')
            ORDER BY j.scheduled_for ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $dueJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $published = 0;
        $failed = 0;
        $errors = [];

        foreach ($dueJobs as $job) {
            $jobId = (int)$job['id'];
            $clientId = (int)$job['client_id'];
            $platform = (string)$job['platform'];
            $campaignId = (int)$job['campaign_id'];
            $campaignName = (string)$job['campaign_name'];
            $message = (string)($job['message'] ?? '');
            $linkUrl = !empty($job['link_url']) ? (string)$job['link_url'] : null;
            $mediaPath = !empty($job['media_path']) ? (string)$job['media_path'] : null;

            if ($logger) {
                $logger("Processing Job #{$jobId} [{$platform}] for '{$campaignName}'", 'info');
            }

            // Enforce account limits & retrieve connection credentials
            $account = null;
            if (!empty($job['account_id'])) {
                $acctStmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
                $acctStmt->execute([(int)$job['account_id']]);
                $account = $acctStmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$account) {
                $acctStmt = $pdo->prepare("SELECT * FROM accounts WHERE client_id = ? AND platform = ? ORDER BY id ASC LIMIT 1");
                $acctStmt->execute([$clientId, $platform]);
                $account = $acctStmt->fetch(PDO::FETCH_ASSOC);
            }

            $maxDaily = $account ? (int)$account['daily_max_limit'] : 15;
            if ($account) {
                $todayStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM jobs 
                    WHERE account_id = ? AND status = 'published' AND DATE(created_at) = DATE('now', 'localtime')
                ");
                $todayStmt->execute([(int)$account['id']]);
            } else {
                $todayStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM jobs 
                    WHERE client_id = ? AND platform = ? AND status = 'published' AND DATE(created_at) = DATE('now', 'localtime')
                ");
                $todayStmt->execute([$clientId, $platform]);
            }
            $todayCount = (int)$todayStmt->fetchColumn();

            if ($todayCount >= $maxDaily) {
                $acctName = $account['label'] ?? $platform;
                $errorMsg = "Daily limit reached for {$acctName} ({$todayCount}/{$maxDaily}). Deferred.";
                $pdo->prepare("UPDATE jobs SET status = 'failed', error_message = ? WHERE id = ?")->execute([$errorMsg, $jobId]);
                $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'warning', ?)")
                    ->execute([$clientId, "Rate limit reached for {$acctName} on Job #{$jobId}", $now]);
                
                if ($logger) {
                    $logger("Rate limit exceeded ({$todayCount}/{$maxDaily})", 'warn');
                }
                $failed++;
                continue;
            }

            // Live Meta API Publishing Execution (Facebook / Instagram)
            $liveSuccess = true;
            $liveNotice = '';
            $publishedPostId = null;

            if ($account && !empty($account['access_token'])) {
                if ($platform === 'facebook' && !empty($account['meta_page_id'])) {
                    $fbRes = MetaGraph::publishToFacebookPage($account['meta_page_id'], $account['access_token'], $message, $linkUrl, $mediaPath);
                    if (!$fbRes['success']) {
                        $liveSuccess = false;
                        $liveNotice = 'Facebook API Error: ' . ($fbRes['error'] ?? 'Unknown');
                    } else {
                        $publishedPostId = (string)($fbRes['post_id'] ?? '');
                        $liveNotice = ' (Live Meta FB Post ID: ' . $publishedPostId . ')';
                    }
                } elseif ($platform === 'instagram' && !empty($account['meta_ig_id'])) {
                    if (!empty($mediaPath)) {
                        // Instagram requires public HTTPS URL to download image
                        if (filter_var($mediaPath, FILTER_VALIDATE_URL) && str_starts_with($mediaPath, 'https://')) {
                            $igRes = MetaGraph::publishToInstagram($account['meta_ig_id'], $account['access_token'], $mediaPath, $message);
                            if (!$igRes['success']) {
                                $liveSuccess = false;
                                $liveNotice = 'Instagram API Error: ' . ($igRes['error'] ?? 'Unknown');
                            } else {
                                $publishedPostId = (string)($igRes['media_id'] ?? '');
                                $liveNotice = ' (Live Meta IG Media ID: ' . $publishedPostId . ')';
                            }
                        } else {
                            $liveNotice = ' (Instagram queued / simulated: Instagram requires public HTTPS URL for image media)';
                        }
                    } else {
                        $liveNotice = ' (Instagram queued: Instagram requires image media)';
                    }
                } elseif ($platform === 'linkedin' && !empty($account['linkedin_urn'])) {
                    $liRes = LinkedInClient::publishPost($account['linkedin_urn'], $account['access_token'], $message, $linkUrl);
                    if (!$liRes['success']) {
                        $liveSuccess = false;
                        $liveNotice = 'LinkedIn API Error: ' . ($liRes['error'] ?? 'Unknown');
                    } else {
                        $publishedPostId = (string)($liRes['post_id'] ?? '');
                        $liveNotice = ' (Live LinkedIn Post ID: ' . $publishedPostId . ')';
                    }
                } elseif ($platform === 'pinterest' && (!empty($account['pinterest_board_id']) || !empty($account['external_id']))) {
                    $boardId = !empty($account['pinterest_board_id']) ? (string)$account['pinterest_board_id'] : (string)$account['external_id'];
                    $pinRes = PinterestClient::publishPin($boardId, $account['access_token'], $campaignName, $message, $linkUrl, $mediaPath);
                    if (!$pinRes['success']) {
                        $liveSuccess = false;
                        $liveNotice = 'Pinterest API Error: ' . ($pinRes['error'] ?? 'Unknown');
                    } else {
                        $publishedPostId = (string)($pinRes['pin_id'] ?? '');
                        $liveNotice = ' (Live Pinterest Pin ID: ' . $publishedPostId . ')';
                    }
                }
            }

            if (!$liveSuccess) {
                $pdo->prepare("UPDATE jobs SET status = 'failed', error_message = ? WHERE id = ?")->execute([$liveNotice, $jobId]);
                $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'error', ?)")
                    ->execute([$clientId, "Live publishing failed on {$platform}: {$liveNotice}", $now]);
                if ($logger) {
                    $logger("Failed: {$liveNotice}", 'error');
                }
                $errors[] = [
                    'job_id' => $jobId,
                    'platform' => $platform,
                    'message' => $liveNotice,
                ];
                $failed++;
                continue;
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE jobs SET status = 'published', response_id = ?, error_message = NULL WHERE id = ?")
                    ->execute([$publishedPostId, $jobId]);
                $targetLabel = $account ? ($account['label'] . ' (' . (platforms()[$platform][0] ?? $platform) . ')') : (platforms()[$platform][0] ?? $platform);
                $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
                    ->execute([$clientId, "Published to {$targetLabel} for '{$campaignName}'{$liveNotice}", $now]);

                $rem = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE campaign_id = ? AND status != 'published'");
                $rem->execute([$campaignId]);
                if ((int)$rem->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaignId]);
                }

                $pdo->commit();
                $published++;
                if ($logger) {
                    $logger("Successfully published{$liveNotice}", 'success');
                }

                // If campaign has auto_boost enabled and this was published to Facebook, trigger cross-account boost
                if (!empty($publishedPostId) && $platform === 'facebook' && !empty($job['auto_boost'])) {
                    self::dispatchAutoBoost($clientId, $publishedPostId, (int)($account['id'] ?? 0), $campaignId);
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $failed++;
                $errors[] = [
                    'job_id' => $jobId,
                    'platform' => $platform,
                    'message' => 'Transaction error: ' . $e->getMessage(),
                ];
                if ($logger) {
                    $logger("Transaction error: " . $e->getMessage(), 'error');
                }
            }
        }

        return ['published' => $published, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Cross-Account Engagement Engine (Growth Pod Auto-Boost)
     * Automatically triggers comments and likes from other connected pages onto a target post
     */
    public static function dispatchAutoBoost(int $clientId, string $targetPostId, int $sourceAccountId = 0, int $campaignId = 0): array {
        $pdo = db();
        $targetPostId = trim($targetPostId);
        if (empty($targetPostId)) {
            return ['boosted' => 0, 'failed' => 0];
        }

        // Fetch all other active Facebook accounts with access tokens
        $stmt = $pdo->prepare("
            SELECT * FROM accounts 
            WHERE connection_status = 'connected' 
              AND platform = 'facebook' 
              AND id != ? 
              AND access_token IS NOT NULL 
              AND access_token != ''
            ORDER BY id ASC
        ");
        $stmt->execute([$sourceAccountId]);
        $boosterAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $defaultComments = [
            "Outstanding work and strategic execution! Great job WSA! 🚀",
            "WSA Digital Agency always delivers top quality results! 🔥",
            "Highly recommended team for digital marketing and brand scaling! ⭐⭐⭐⭐⭐",
            "Brilliant insights and creativity as always! 👏",
            "Consistent quality and dedicated team. Strongly recommend!",
            "Top digital marketing agency with proven results! 👍",
            "Awesome work, always exceeding expectations! 💯",
            "Superb expertise and dedication by WSA Digital Agency!",
        ];

        $boostedCount = 0;
        $failedCount = 0;

        foreach ($boosterAccounts as $idx => $bAcc) {
            $token = (string)$bAcc['access_token'];
            if (empty($token)) {
                continue;
            }

            $commentText = $defaultComments[$idx % count($defaultComments)];

            // 1. Leave Comment via Meta Graph API
            $cRes = MetaGraph::commentOnFacebookPost($targetPostId, $token, $commentText);

            // 2. Leave Like via Meta Graph API
            MetaGraph::likeFacebookPost($targetPostId, $token);

            $status = $cRes['success'] ? 'published' : 'failed';
            $errMsg = $cRes['error'] ?? null;

            // Record in comments audit log
            $pdo->prepare("
                INSERT INTO comments(client_id, account_id, platform, post_external_id, comment_text, response_id, status, error_message, created_at)
                VALUES(?, ?, 'facebook', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $clientId,
                (int)$bAcc['id'],
                $targetPostId,
                $commentText,
                $cRes['comment_id'] ?? null,
                $status,
                $errMsg,
                now()
            ]);

            if ($cRes['success']) {
                $boostedCount++;
            } else {
                $failedCount++;
            }
        }

        $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'info', ?)")
            ->execute([$clientId, "Auto-Boost dispatched on Post #{$targetPostId}: {$boostedCount} supportive comments dropped", now()]);

        return ['boosted' => $boostedCount, 'failed' => $failedCount];
    }

    /**
     * Autonomous Group Distribution Auto-Pilot for Page Posts/Reels (e.g. WSA Digital Agency)
     * Automatically pulls the latest post/reel from the Facebook Page, picks groups in round-robin rotation,
     * and schedules them safely.
     */
    public static function generateGroupAutoPilotQueue(
        int $clientId = 2,
        int $sourceAccountId = 24,
        int $dailyShares = 5,
        int $intervalMinutes = 30
    ): array {
        $pdo = db();

        // 1. Fetch Account credentials
        $accSt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $accSt->execute([$sourceAccountId]);
        $account = $accSt->fetch(PDO::FETCH_ASSOC);

        if (!$account || empty($account['access_token']) || empty($account['external_id'])) {
            return [
                'success' => false,
                'error' => "Source Facebook account (#{$sourceAccountId}) not found or missing access token."
            ];
        }

        // 2. Fetch latest post/reel from Facebook Page via Meta Graph API
        $posts = MetaGraph::fetchLatestPagePosts((string)$account['external_id'], (string)$account['access_token'], 5);
        if (empty($posts)) {
            return [
                'success' => false,
                'error' => "No recent posts found for {$account['label']}."
            ];
        }

        $latestPost = $posts[0];
        $postUrl = !empty($latestPost['permalink_url']) 
            ? (string)$latestPost['permalink_url'] 
            : ('https://www.facebook.com/' . $latestPost['id']);
        $postCaption = trim((string)($latestPost['message'] ?? ''));
        $postId = (string)$latestPost['id'];

        // 3. Select target groups using smart Round-Robin rotation (least recently shared first)
        $grpSt = $pdo->prepare("
            SELECT * FROM group_targets 
            WHERE client_id = ? AND is_enabled = 1 
            ORDER BY CASE WHEN last_shared_at IS NULL THEN 0 ELSE 1 END ASC, last_shared_at ASC, id ASC 
            LIMIT ?
        ");
        $grpSt->bindValue(1, $clientId, PDO::PARAM_INT);
        $grpSt->bindValue(2, $dailyShares, PDO::PARAM_INT);
        $grpSt->execute();
        $targetGroups = $grpSt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($targetGroups)) {
            return [
                'success' => false,
                'error' => "No active target groups available in pool. Please import or enable groups first."
            ];
        }

        // 4. Fetch enabled sender profiles
        $sendersSt = $pdo->prepare("SELECT * FROM sender_profiles WHERE client_id = ? AND is_enabled = 1 ORDER BY id ASC");
        $sendersSt->execute([$clientId]);
        $senders = $sendersSt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($senders)) {
            $senders = [['id' => null, 'name' => 'Direct Facebook Account']];
        }

        // 5. Schedule entries in group_share_queue
        $insQueue = $pdo->prepare("
            INSERT INTO group_share_queue(client_id, campaign_id, group_id, sender_profile_id, post_url, caption, status, scheduled_for, created_at)
            VALUES(?, NULL, ?, ?, ?, ?, 'queued', ?, datetime('now'))
        ");

        $nowTs = time() + 180; // Starts in 3 mins
        $scheduledCount = 0;
        $totalSenders = count($senders);

        foreach ($targetGroups as $i => $tg) {
            $assignedSender = $senders[$i % $totalSenders];
            $scheduledTime = date('Y-m-d H:i:s', $nowTs + ($i * $intervalMinutes * 60));

            $insQueue->execute([
                $clientId,
                $tg['id'],
                $assignedSender['id'],
                $postUrl,
                $postCaption,
                $scheduledTime,
            ]);
            $scheduledCount++;
        }

        // Activity log
        $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
            ->execute([
                $clientId,
                "🤖 Auto-Pilot queued {$scheduledCount} group shares for latest {$account['label']} post",
                now()
            ]);

        return [
            'success' => true,
            'count' => $scheduledCount,
            'post' => $latestPost,
            'post_url' => $postUrl,
            'account' => $account['label'],
            'groups' => array_column($targetGroups, 'name'),
            'interval_minutes' => $intervalMinutes
        ];
    }

    /**
     * Process due items in group_share_queue
     * Automatically called by CRON worker and manual runner
     *
     * @param int $limit
     * @return array [processed => int, shared => int]
     */
    public static function processGroupShareQueue(int $limit = 10): array
    {
        $pdo = db();
        $now = now();

        $stmt = $pdo->prepare("
            SELECT q.*, 
                   sa.label AS sender_label, sa.access_token AS page_token, sa.external_id AS page_id,
                   gt.name AS group_name, gt.group_url
            FROM group_share_queue q
            LEFT JOIN accounts sa ON sa.id = q.sender_account_id
            JOIN group_targets gt ON gt.id = q.group_id
            WHERE q.status = 'queued'
              AND q.scheduled_for <= ?
            ORDER BY q.scheduled_for ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $now, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $dueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        $shared = 0;

        foreach ($dueItems as $item) {
            $itemId = (int)$item['id'];
            $groupUrl = (string)($item['group_url'] ?? '');
            $postUrl = (string)($item['post_url'] ?? '');
            $caption = (string)($item['caption'] ?? '');
            $pageToken = (string)($item['page_token'] ?? '');
            $senderLabel = (string)($item['sender_label'] ?? 'WSA Page');
            $groupName = (string)($item['group_name'] ?? 'Facebook Group');

            $publishedViaApi = false;
            $sharedPostUrl = null;

            // 1. Attempt Meta Graph API dispatch if token and slug available
            if (!empty($pageToken) && !empty($groupUrl)) {
                if (preg_match('/groups\/([^\/\?]+)/', $groupUrl, $m)) {
                    $groupSlug = $m[1];
                    $apiRes = MetaGraph::publishToFacebookGroup($groupSlug, $pageToken, $caption ?: $postUrl, $postUrl);
                    if (!empty($apiRes['success'])) {
                        $publishedViaApi = true;
                        $sharedPostUrl = $apiRes['post_url'] ?? null;
                    }
                }
            }

            // 2. Only mark as shared if Graph API actually succeeded
            if ($publishedViaApi && !empty($sharedPostUrl)) {
                $pdo->prepare("UPDATE group_share_queue SET status = 'shared', shared_at = ?, shared_post_url = ?, error_message = NULL WHERE id = ?")
                    ->execute([$now, $sharedPostUrl, $itemId]);

                // Update target group stats
                $pdo->prepare("UPDATE group_targets SET total_shares = total_shares + 1, last_shared_at = ? WHERE id = ?")
                    ->execute([$now, (int)$item['group_id']]);

                // Activity log
                $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
                    ->execute([
                        $item['client_id'] ?: 2,
                        "Group share completed to {$groupName} via {$senderLabel} (API)",
                        $now
                    ]);

                $shared++;
            } else {
                // Meta API blocked direct bot posting (Meta 2024 policy)
                // Keep in queue and flag for 1-Click manual dispatch
                $pdo->prepare("UPDATE group_share_queue SET error_message = 'Meta API group restriction: Use 1-Click Share to post as page' WHERE id = ?")
                    ->execute([$itemId]);
            }

            $processed++;
        }

        return [
            'processed' => $processed,
            'shared' => $shared,
        ];
    }
}
