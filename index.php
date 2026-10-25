<?php
declare(strict_types=1);

/**
 * WSA Multi-Channel Publisher - Front Controller & Router
 * Zero-Setup Modular Architecture Edition
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/queue.php';
require_once __DIR__ . '/includes/meta.php';
require_once __DIR__ . '/includes/linkedin.php';
require_once __DIR__ . '/includes/pinterest.php';

// Initialize Database singleton & auto-migrations
$pdo = db();

$page = (string)($_GET['page'] ?? (logged_in() ? 'dashboard' : 'login'));

// ----------------------------------------------------------------------
// Authentication Routes (Public)
// ----------------------------------------------------------------------
if ($page === 'login') {
    if (logged_in()) {
        redirect('index.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $ip = client_ip();

        if (!check_login_rate_limit($ip)) {
            flash('error', 'Too many failed login attempts. Please wait 15 minutes before trying again.');
            redirect('index.php?page=login');
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $st = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $admin = $st->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            clear_login_attempts($ip);
            session_regenerate_id(true); // Mitigate Session Fixation (CWE-384)
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['csrf'] = bin2hex(random_bytes(24)); // Rotate CSRF token
            redirect('index.php');
        }

        record_login_attempt($ip);
        flash('error', 'Invalid email or password.');
        redirect('index.php?page=login');
    }

    require __DIR__ . '/views/login.php';
    exit;
}

if ($page === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('index.php?page=login');
}

// ----------------------------------------------------------------------
// 1-Click Fast Sync API for Facebook Groups (Supports CORS from browser)
// ----------------------------------------------------------------------
if ($page === 'api-import-groups') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $cId = (int)($activeClientId ?? 2);
    if ($cId <= 0) $cId = 2;

    $rawInput = file_get_contents('php://input');
    $data = json_decode((string)$rawInput, true);

    $groups = $data['groups'] ?? [];
    $accountId = !empty($data['account_id']) ? (int)$data['account_id'] : null;
    $defaultNiche = trim((string)($data['niche'] ?? 'General'));

    if (empty($groups) && !empty($_POST['groups'])) {
        $groups = is_array($_POST['groups']) ? $_POST['groups'] : json_decode((string)$_POST['groups'], true);
    }

    if (empty($groups)) {
        echo json_encode(['success' => false, 'error' => 'No groups received.']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO group_targets(client_id, account_id, name, group_url, niche_category, member_count, is_enabled, created_at) VALUES(?, ?, ?, ?, ?, 'Active', 1, datetime('now'))");
    $chk = $pdo->prepare("SELECT id FROM group_targets WHERE client_id = ? AND group_url = ?");
    $added = 0;
    $duplicates = 0;

    foreach ($groups as $g) {
        $url = trim((string)($g['url'] ?? ''));
        $name = trim((string)($g['name'] ?? ''));

        if (!preg_match('~facebook\.com/groups/([a-zA-Z0-9\.\_\-]+)~i', $url, $m)) {
            continue;
        }

        $slug = $m[1];
        if (in_array(strtolower($slug), ['feed', 'joins', 'discover', 'create', 'search', 'category'], true)) {
            continue;
        }

        $cleanUrl = 'https://www.facebook.com/groups/' . $slug;
        $name = preg_replace('/(Last active|You last visited|last visited).*$/i', '', $name);
        $name = trim($name);

        if ($name === '' || strtolower($name) === 'view group' || strtolower($name) === 'view' || preg_match('~^https?://~i', $name)) {
            $name = ucwords(str_replace(['-', '_', '.'], ' ', $slug));
        }

        $chk->execute([$cId, $cleanUrl]);
        if ($chk->fetchColumn()) {
            $duplicates++;
            continue;
        }

        $ins->execute([$cId, $accountId, $name, $cleanUrl, $defaultNiche]);
        $added++;
    }

    $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
        ->execute([$cId, "Direct synced {$added} Facebook profile groups ({$duplicates} skipped duplicates)", now()]);

    echo json_encode([
        'success' => true,
        'added' => $added,
        'duplicates' => $duplicates,
        'total' => count($groups),
        'message' => "Successfully imported {$added} groups into WSA Social!"
    ]);
    exit;
}

// ----------------------------------------------------------------------
// Chrome Extension Automation APIs (Zero-Click Group Sharing)
// ----------------------------------------------------------------------
if ($page === 'api-group-queue-next') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $ignoreTime = !empty($_GET['ignore_time']) && ($_GET['ignore_time'] === '1' || $_GET['ignore_time'] === 'true');
    $itemId = !empty($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    $now = now();

    if ($itemId > 0) {
        $st = $pdo->prepare("SELECT q.id, q.client_id, q.group_id, q.post_url, q.caption, q.status, q.scheduled_for,
                                    gt.name AS group_name, gt.group_url,
                                    sp.id AS sender_id, sp.name AS sender_name,
                                    a.id AS account_id, a.label AS account_name, a.external_id AS account_external_id
                             FROM group_share_queue q
                             JOIN group_targets gt ON gt.id = q.group_id
                             LEFT JOIN sender_profiles sp ON sp.id = q.sender_profile_id
                             LEFT JOIN accounts a ON a.id = q.sender_account_id
                             WHERE q.id = ?
                             LIMIT 1");
        $st->execute([$itemId]);
    } elseif ($ignoreTime) {
        $st = $pdo->prepare("SELECT q.id, q.client_id, q.group_id, q.post_url, q.caption, q.status, q.scheduled_for,
                                    gt.name AS group_name, gt.group_url,
                                    sp.id AS sender_id, sp.name AS sender_name,
                                    a.id AS account_id, a.label AS account_name, a.external_id AS account_external_id
                             FROM group_share_queue q
                             JOIN group_targets gt ON gt.id = q.group_id
                             LEFT JOIN sender_profiles sp ON sp.id = q.sender_profile_id
                             LEFT JOIN accounts a ON a.id = q.sender_account_id
                             WHERE q.status = 'queued'
                             ORDER BY q.scheduled_for ASC, q.id ASC
                             LIMIT 1");
        $st->execute();
    } else {
        // Scheduled time check with a 60-second window
        $threshold = date('Y-m-d H:i:s', time() + 60);
        $st = $pdo->prepare("SELECT q.id, q.client_id, q.group_id, q.post_url, q.caption, q.status, q.scheduled_for,
                                    gt.name AS group_name, gt.group_url,
                                    sp.id AS sender_id, sp.name AS sender_name,
                                    a.id AS account_id, a.label AS account_name, a.external_id AS account_external_id
                             FROM group_share_queue q
                             JOIN group_targets gt ON gt.id = q.group_id
                             LEFT JOIN sender_profiles sp ON sp.id = q.sender_profile_id
                             LEFT JOIN accounts a ON a.id = q.sender_account_id
                             WHERE q.status = 'queued' AND q.scheduled_for <= ?
                             ORDER BY q.scheduled_for ASC, q.id ASC
                             LIMIT 1");
        $st->execute([$threshold]);
    }

    $item = $st->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $caption = trim((string)($item['caption'] ?? ''));
        $postUrl = trim((string)($item['post_url'] ?? ''));
        $senderLabel = $item['account_name'] ?: ($item['sender_name'] ?: 'WSA Digital Agency');

        echo json_encode([
            'success' => true,
            'found' => true,
            'item' => [
                'id' => (int)$item['id'],
                'group_id' => (int)$item['group_id'],
                'group_name' => $item['group_name'],
                'group_url' => $item['group_url'],
                'post_url' => $postUrl,
                'caption' => $caption,
                'full_content' => $caption !== '' ? ($caption . "\n\n" . $postUrl) : $postUrl,
                'sender_name' => $senderLabel,
                'account_external_id' => $item['account_external_id'] ?? null,
                'scheduled_for' => $item['scheduled_for']
            ]
        ], JSON_UNESCAPED_SLASHES);
    } else {
        $nextSt = $pdo->query("SELECT scheduled_for FROM group_share_queue WHERE status = 'queued' ORDER BY scheduled_for ASC LIMIT 1");
        $nextScheduled = $nextSt ? $nextSt->fetchColumn() : null;

        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => 'No queued items due right now.',
            'next_scheduled_for' => $nextScheduled,
            'server_time' => now()
        ]);
    }
    exit;
}

if ($page === 'api-group-queue-complete') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true) ?: $_POST;

    $queueId = (int)($data['queue_id'] ?? 0);
    $status = trim((string)($data['status'] ?? 'shared'));
    if (!in_array($status, ['shared', 'pending_approval', 'failed'], true)) {
        $status = 'shared';
    }
    $sharedPostUrl = trim((string)($data['shared_post_url'] ?? ''));
    $errorMessage = trim((string)($data['error_message'] ?? ''));

    if ($queueId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing queue_id']);
        exit;
    }

    $st = $pdo->prepare("SELECT q.*, sp.id AS sender_id, gt.id AS group_id, gt.name AS group_name, gt.group_url 
                         FROM group_share_queue q 
                         LEFT JOIN sender_profiles sp ON sp.id = q.sender_profile_id 
                         JOIN group_targets gt ON gt.id = q.group_id 
                         WHERE q.id = ?");
    $st->execute([$queueId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Queue item not found']);
        exit;
    }

    $now = now();
    $finalUrl = null;
    if ($status === 'shared' && !empty($sharedPostUrl)) {
        if (str_contains($sharedPostUrl, '/posts/') || str_contains($sharedPostUrl, '/permalink/') || str_contains($sharedPostUrl, '/reel/')) {
            $finalUrl = $sharedPostUrl;
        }
    }

    $upd = $pdo->prepare("UPDATE group_share_queue 
                          SET status = ?, shared_at = ?, shared_post_url = ?, error_message = ? 
                          WHERE id = ?");
    $upd->execute([$status, $now, $finalUrl, $errorMessage ?: null, $queueId]);

    if ($status === 'shared') {
        if (!empty($item['sender_id'])) {
            $pdo->prepare("UPDATE sender_profiles SET shares_today = shares_today + 1, last_share_at = ? WHERE id = ?")
                ->execute([$now, (int)$item['sender_id']]);
        }
        if (!empty($item['group_id'])) {
            $pdo->prepare("UPDATE group_targets SET total_shares = total_shares + 1, last_shared_at = ? WHERE id = ?")
                ->execute([$now, (int)$item['group_id']]);
        }
    }

    $statusLabel = ($status === 'pending_approval') ? 'Pending Admin Approval' : ($status === 'failed' ? 'Failed' : 'Published Live');
    $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, ?, ?)")
        ->execute([
            $item['client_id'], 
            "Chrome Extension: Group share for '{$item['group_name']}' recorded as {$statusLabel}", 
            $status === 'failed' ? 'failed' : 'success', 
            $now
        ]);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'group_name' => $item['group_name'],
        'shared_post_url' => $finalUrl,
        'message' => "Successfully recorded share as {$statusLabel}"
    ]);
    exit;
}

if ($page === 'api-group-queue-status') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $queuedCount = (int)$pdo->query("SELECT COUNT(*) FROM group_share_queue WHERE status = 'queued'")->fetchColumn();
    $sharedCount = (int)$pdo->query("SELECT COUNT(*) FROM group_share_queue WHERE status = 'shared'")->fetchColumn();
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM group_share_queue WHERE status = 'pending_approval'")->fetchColumn();
    $todayShared = (int)$pdo->query("SELECT COUNT(*) FROM group_share_queue WHERE status = 'shared' AND DATE(shared_at) = DATE('now', 'localtime')")->fetchColumn();

    $nextDue = $pdo->query("SELECT q.id, q.scheduled_for, gt.name group_name, a.label acc_name 
                            FROM group_share_queue q 
                            JOIN group_targets gt ON gt.id = q.group_id 
                            LEFT JOIN accounts a ON a.id = q.sender_account_id 
                            WHERE q.status = 'queued' 
                            ORDER BY q.scheduled_for ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'server_time' => now(),
        'queued_count' => $queuedCount,
        'shared_count' => $sharedCount,
        'pending_count' => $pendingCount,
        'today_shared_count' => $todayShared,
        'next_due' => $nextDue ?: null
    ]);
    exit;
}

// ----------------------------------------------------------------------
// Public Legal & Compliance Routes (Meta, LinkedIn, Pinterest App Review)
// Accessible without login so OAuth reviewers, automated crawlers, and users can view
// ----------------------------------------------------------------------
if ($page === 'privacy') {
    require __DIR__ . '/views/privacy.php';
    exit;
}

if ($page === 'terms') {
    require __DIR__ . '/views/terms.php';
    exit;
}

if ($page === 'data-deletion') {
    require __DIR__ . '/views/data_deletion.php';
    exit;
}

// ----------------------------------------------------------------------
// Authentication Guard & Workspace Context Initialization
// ----------------------------------------------------------------------
if (!logged_in()) {
    redirect('index.php?page=login');
}

$allClients = $pdo->query("SELECT * FROM clients WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$activeClientId = (int)($_SESSION['active_client_id'] ?? 0);
if ($activeClientId === 0 && count($allClients) === 1) {
    $activeClientId = (int)$allClients[0]['id'];
    $_SESSION['active_client_id'] = $activeClientId;
} elseif ($activeClientId > 0) {
    $found = false;
    foreach ($allClients as $cl) {
        if ((int)$cl['id'] === $activeClientId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $activeClientId = count($allClients) === 1 ? (int)$allClients[0]['id'] : 0;
        $_SESSION['active_client_id'] = $activeClientId;
    }
}
$activeClientName = 'All Workspaces';
foreach ($allClients as $cl) {
    if ((int)$cl['id'] === $activeClientId) {
        $activeClientName = (string)$cl['name'];
        break;
    }
}


// ----------------------------------------------------------------------
// POST Action Handlers (Protected)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($page === 'client-add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Client name cannot be blank.');
            redirect('index.php?page=clients');
        }
        $tz = trim((string)($_POST['timezone'] ?? 'Asia/Kolkata'));
        if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            $tz = 'Asia/Kolkata';
        }
        $pdo->prepare("INSERT INTO clients(name,timezone,created_at) VALUES(?,?,?)")->execute([$name, $tz, now()]);
        flash('success', 'Client added successfully.');
        redirect('index.php?page=clients');
    }

    if ($page === 'client-toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT is_active, name FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $newStatus = ((int)$client['is_active'] === 1) ? 0 : 1;
                $pdo->prepare("UPDATE clients SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
                $statusWord = $newStatus === 1 ? 'activated' : 'deactivated';
                $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                    ->execute([$id, "Client workspace {$statusWord}: {$client['name']}", 'info', now()]);
                flash('success', "Client {$client['name']} successfully {$statusWord}.");
            } else {
                flash('error', 'Client not found.');
            }
        }
        redirect('index.php?page=clients');
    }

    if ($page === 'client-delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
                $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                    ->execute([null, "Client workspace deleted: {$client['name']}", 'warning', now()]);
                flash('success', "Client workspace {$client['name']} and all associated records deleted.");
            } else {
                flash('error', 'Client not found.');
            }
        }
        redirect('index.php?page=clients');
    }

    if ($page === 'account-add') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $platform = (string)($_POST['platform'] ?? '');
        $label = trim((string)($_POST['label'] ?? ''));
        $externalId = trim((string)($_POST['external_id'] ?? ''));
        $accessToken = trim((string)($_POST['access_token'] ?? ''));
        $pageCategory = trim((string)($_POST['page_category'] ?? ''));
        $baseLimit = max(1, (int)($_POST['daily_base_limit'] ?? 5));
        $maxLimit = max($baseLimit, (int)($_POST['daily_max_limit'] ?? 15));

        if ($clientId <= 0 || !array_key_exists($platform, platforms()) || $label === '') {
            flash('error', 'Please provide a valid client workspace, supported platform, and account/page label.');
            redirect('index.php?page=accounts');
        }

        $chk = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE id = ? AND is_active = 1");
        $chk->execute([$clientId]);
        if ((int)$chk->fetchColumn() === 0) {
            flash('error', 'Selected client does not exist or is inactive.');
            redirect('index.php?page=accounts');
        }

        $status = $accessToken !== '' ? 'connected' : 'not_connected';
        $metaPageId = ($platform === 'facebook' && $externalId !== '') ? $externalId : null;
        $metaIgId = ($platform === 'instagram' && $externalId !== '') ? $externalId : null;
        $liUrn = ($platform === 'linkedin' && $externalId !== '') ? $externalId : null;
        $pinBoardId = ($platform === 'pinterest' && $externalId !== '') ? $externalId : null;

        $pdo->prepare("
            INSERT INTO accounts(client_id,platform,label,external_id,connection_status,access_token,meta_page_id,meta_ig_id,linkedin_urn,pinterest_board_id,page_category,daily_base_limit,daily_max_limit,created_at) 
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $clientId, $platform, $label, $externalId, $status,
            $accessToken !== '' ? $accessToken : null,
            $metaPageId, $metaIgId, $liUrn, $pinBoardId,
            $pageCategory !== '' ? $pageCategory : null,
            $baseLimit, $maxLimit, now()
        ]);

        $notice = $status === 'connected' ? ' and marked active with API token' : '';
        flash('success', "Account profile '{$label}' added successfully{$notice}.");
        redirect('index.php?page=accounts');
    }

    if ($page === 'account-delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT a.label, c.id client_id FROM accounts a JOIN clients c ON c.id = a.client_id WHERE a.id = ?");
            $stmt->execute([$id]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc) {
                $pdo->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);
                $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                    ->execute([(int)$acc['client_id'], "Account profile deleted: {$acc['label']}", 'info', now()]);
                flash('success', "Account profile {$acc['label']} removed.");
            } else {
                flash('error', 'Account profile not found.');
            }
        }
        redirect('index.php?page=accounts');
    }

    if ($page === 'meta-direct-sync') {
        $targetClientId = (int)($_POST['client_id'] ?? ($activeClientId ?? 2));
        if ($targetClientId <= 0) {
            $targetClientId = 2;
        }
        $rawToken = trim((string)($_POST['direct_meta_token'] ?? ''));
        if ($rawToken === '') {
            flash('error', 'Please paste a valid Meta User or Page Access Token.');
            redirect('index.php?page=accounts');
        }

        $res = MetaGraph::syncWithDirectToken($pdo, $rawToken);
        if (!$res['success']) {
            flash('error', 'Meta Token Sync Failed: ' . ($res['error'] ?? 'Unknown error'));
            redirect('index.php?page=accounts');
        }

        $pages = $res['pages'] ?? [];
        $expiresAt = $res['expires_at'] ?? date('Y-m-d H:i:s', time() + 5184000);
        $connectedCount = 0;

        foreach ($pages as $p) {
            $pageId = (string)($p['id'] ?? '');
            $pageName = (string)($p['name'] ?? 'Facebook Page');
            $pageToken = (string)($p['access_token'] ?? '');

            $chkFb = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'facebook' AND meta_page_id = ?");
            $chkFb->execute([$targetClientId, $pageId]);
            $existingFbId = $chkFb->fetchColumn();

            if ($existingFbId) {
                $pdo->prepare("UPDATE accounts SET access_token = ?, token_expires_at = ?, label = ?, connection_status = 'connected' WHERE id = ?")
                    ->execute([$pageToken, $expiresAt, $pageName . ' (Page)', $existingFbId]);
            } else {
                $pdo->prepare("INSERT INTO accounts(client_id,platform,label,external_id,connection_status,access_token,token_expires_at,meta_page_id,created_at) VALUES(?,?,?,?,'connected',?,?,?,?)")
                    ->execute([$targetClientId, 'facebook', $pageName . ' (Page)', $pageId, $pageToken, $expiresAt, $pageId, now()]);
            }
            $connectedCount++;

            if (!empty($p['instagram_business_account']['id'])) {
                $igId = (string)$p['instagram_business_account']['id'];
                $igUsername = (string)($p['instagram_business_account']['username'] ?? $pageName . '_ig');

                $chkIg = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'instagram' AND meta_ig_id = ?");
                $chkIg->execute([$targetClientId, $igId]);
                $existingIgId = $chkIg->fetchColumn();

                if ($existingIgId) {
                    $pdo->prepare("UPDATE accounts SET access_token = ?, token_expires_at = ?, label = ?, connection_status = 'connected', meta_page_id = ? WHERE id = ?")
                        ->execute([$pageToken, $expiresAt, '@' . $igUsername . ' (Instagram)', $pageId, $existingIgId]);
                } else {
                    $pdo->prepare("INSERT INTO accounts(client_id,platform,label,external_id,connection_status,access_token,token_expires_at,meta_page_id,meta_ig_id,created_at) VALUES(?,?,?,?,'connected',?,?,?,?,?)")
                        ->execute([$targetClientId, 'instagram', '@' . $igUsername . ' (Instagram)', $igId, $pageToken, $expiresAt, $pageId, $igId, now()]);
                }
                $connectedCount++;
            }
        }

        $pdo->prepare("UPDATE jobs SET status = 'queued', error_message = NULL, scheduled_for = ? WHERE client_id = ? AND status = 'failed' AND error_message LIKE '%(#200)%'")
            ->execute([now(), $targetClientId]);
        $autoRetry = QueueEngine::process(25);
        $extraMsg = !empty($autoRetry['published']) ? " Also auto-published {$autoRetry['published']} pending post(s)!" : "";

        $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
            ->execute([$targetClientId, "Meta Direct Token Sync: {$connectedCount} channel profile(s) upgraded & linked via App ID/Secret", 'success', now()]);

        flash('success', "🚀 Token Upgraded & Synced! {$connectedCount} Facebook Page & Instagram account(s) updated with long-lived tokens.{$extraMsg}");
        redirect('index.php?page=campaigns');
    }

    if ($page === 'settings-meta') {
        $appId = trim((string)($_POST['meta_app_id'] ?? ''));
        $appSecret = trim((string)($_POST['meta_app_secret'] ?? ''));
        $redirectUri = trim((string)($_POST['meta_redirect_uri'] ?? ''));

        set_system_setting('meta_app_id', $appId);
        if ($appSecret !== '') {
            set_system_setting('meta_app_secret', $appSecret);
        }
        set_system_setting('meta_redirect_uri', $redirectUri);

        $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
            ->execute([null, 'Meta Graph API credentials updated in system settings', 'info', now()]);

        flash('success', 'Meta Graph API credentials saved successfully.');
        redirect('index.php?page=settings');
    }

    if ($page === 'settings-linkedin') {
        $clientId = trim((string)($_POST['linkedin_client_id'] ?? ''));
        $clientSecret = trim((string)($_POST['linkedin_client_secret'] ?? ''));
        $redirectUri = trim((string)($_POST['linkedin_redirect_uri'] ?? ''));

        set_system_setting('linkedin_client_id', $clientId);
        if ($clientSecret !== '') {
            set_system_setting('linkedin_client_secret', $clientSecret);
        }
        set_system_setting('linkedin_redirect_uri', $redirectUri);

        $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
            ->execute([null, 'LinkedIn API credentials updated in system settings', 'info', now()]);

        flash('success', 'LinkedIn API credentials saved successfully.');
        redirect('index.php?page=settings');
    }

    if ($page === 'settings-pinterest') {
        $appId = trim((string)($_POST['pinterest_app_id'] ?? ''));
        $appSecret = trim((string)($_POST['pinterest_app_secret'] ?? ''));
        $redirectUri = trim((string)($_POST['pinterest_redirect_uri'] ?? ''));

        set_system_setting('pinterest_app_id', $appId);
        if ($appSecret !== '') {
            set_system_setting('pinterest_app_secret', $appSecret);
        }
        set_system_setting('pinterest_redirect_uri', $redirectUri);

        $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
            ->execute([null, 'Pinterest API credentials updated in system settings', 'info', now()]);

        flash('success', 'Pinterest API credentials saved successfully.');
        redirect('index.php?page=settings');
    }

    if ($page === 'comment-add') {
        $accountId = (int)($_POST['account_id'] ?? 0);
        $postId = trim((string)($_POST['post_id'] ?? ''));
        $commentText = trim((string)($_POST['comment_text'] ?? ''));

        if ($accountId <= 0 || empty($postId) || empty($commentText)) {
            flash('error', 'Please choose an active account, post ID / URN, and write a comment.');
            redirect('index.php?page=comments');
        }

        $stmt = $pdo->prepare("SELECT a.*, c.name client_name FROM accounts a JOIN clients c ON c.id=a.client_id WHERE a.id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            flash('error', 'Selected account profile not found.');
            redirect('index.php?page=comments');
        }

        $platform = (string)$account['platform'];
        $clientId = (int)$account['client_id'];
        $accessToken = (string)($account['access_token'] ?? '');

        if (empty($accessToken)) {
            flash('error', "Account '{$account['label']}' is not authenticated with an OAuth Access Token. Connect it first via OAuth.");
            redirect('index.php?page=comments');
        }

        $success = false;
        $responseId = null;
        $errorMsg = null;

        if ($platform === 'facebook') {
            $pageId = (string)($account['meta_page_id'] ?? '');
            $res = MetaGraph::commentOnFacebookPost($postId, $accessToken, $commentText);
            if ($res['success']) {
                $success = true;
                $responseId = (string)($res['comment_id'] ?? '');
            } else {
                $errorMsg = (string)($res['error'] ?? 'Facebook commenting failed');
            }
        } elseif ($platform === 'instagram') {
            $res = MetaGraph::commentOnInstagramMedia($postId, $accessToken, $commentText);
            if ($res['success']) {
                $success = true;
                $responseId = (string)($res['comment_id'] ?? '');
            } else {
                $errorMsg = (string)($res['error'] ?? 'Instagram commenting failed');
            }
        } elseif ($platform === 'linkedin') {
            $actorUrn = (string)($account['linkedin_urn'] ?? '');
            if (empty($actorUrn)) {
                $errorMsg = 'LinkedIn Author URN missing. Please reconnect LinkedIn profile.';
            } else {
                $res = LinkedInClient::commentOnPost($postId, $actorUrn, $accessToken, $commentText);
                if ($res['success']) {
                    $success = true;
                    $responseId = (string)($res['comment_id'] ?? '');
                } else {
                    $errorMsg = (string)($res['error'] ?? 'LinkedIn commenting failed');
                }
            }
        } elseif ($platform === 'pinterest') {
            $res = PinterestClient::commentOnPin($postId, $accessToken, $commentText);
            if ($res['success']) {
                $success = true;
                $responseId = (string)($res['comment_id'] ?? '');
            } else {
                $errorMsg = (string)($res['error'] ?? 'Pinterest commenting failed');
            }
        } else {
            $errorMsg = "Direct commenting API is currently configured for Meta (Facebook/Instagram), LinkedIn, and Pinterest.";
        }

        // Record comment in database
        $pdo->prepare("
            INSERT INTO comments(client_id, account_id, platform, post_external_id, comment_text, response_id, status, error_message, created_at)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $clientId,
            $accountId,
            $platform,
            $postId,
            $commentText,
            $responseId,
            $success ? 'published' : 'failed',
            $errorMsg,
            now()
        ]);

        if ($success) {
            $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                ->execute([$clientId, "Auto-comment published on {$platform} for Post #{$postId}", 'success', now()]);
            flash('success', "Comment successfully posted on {$platform}! ID: " . ($responseId ?: 'OK'));
        } else {
            $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                ->execute([$clientId, "Auto-comment error on {$platform}: {$errorMsg}", 'error', now()]);
            flash('error', "Commenting failed: {$errorMsg}");
        }

        redirect('index.php?page=comments');
    }

    if ($page === 'comment-logs-clear') {
        $cId = (int)($activeClientId ?: 2);
        $pdo->prepare("DELETE FROM comments WHERE client_id = ? AND status = 'failed'")->execute([$cId]);
        flash('success', 'Cleared previous failed comment logs from history.');
        redirect('index.php?page=comments');
    }

    if ($page === 'activity-clear') {
        $cId = (int)($activeClientId ?: 0);
        if ($cId > 0) {
            $pdo->prepare("DELETE FROM activity WHERE client_id = ?")->execute([$cId]);
        } else {
            $pdo->exec("DELETE FROM activity");
        }
        flash('success', 'Activity audit trail history cleared.');
        redirect('index.php');
    }

    if ($page === 'boost-comments') {
        $targetPostId = trim((string)($_POST['target_post_id'] ?? ''));
        $selectedBoosterIds = array_filter(array_map('intval', (array)($_POST['booster_account_ids'] ?? [])));
        $doLike = !empty($_POST['do_like']);
        $commentsPoolRaw = trim((string)($_POST['comments_pool'] ?? ''));

        $targetPostId = extract_social_post_id($targetPostId);

        if (empty($targetPostId)) {
            flash('error', 'Please provide a valid Target Post ID or Facebook Post URL.');
            redirect('index.php?page=comments');
        }

        if (empty($selectedBoosterIds)) {
            flash('error', 'Please select at least one booster account to engage with the post.');
            redirect('index.php?page=comments');
        }

        $commentsList = array_values(array_filter(array_map('trim', explode("\n", $commentsPoolRaw))));
        if (empty($commentsList)) {
            $commentsList = [
                "Outstanding work and strategic execution! Great job WSA! 🚀",
                "WSA Digital Agency always delivers top quality results! 🔥",
                "Highly recommended team for digital marketing and brand scaling! ⭐⭐⭐⭐⭐",
                "Brilliant insights and creativity as always! 👏",
                "Consistent quality and dedicated team. Strongly recommend!",
                "Top digital marketing agency with proven results! 👍",
                "Awesome work, always exceeding expectations! 💯",
                "Superb expertise and dedication by WSA Digital Agency!",
            ];
        }

        $inPlaceholders = implode(',', array_fill(0, count($selectedBoosterIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id IN ($inPlaceholders) AND access_token IS NOT NULL AND access_token != ''");
        $stmt->execute($selectedBoosterIds);
        $boosters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $boostedComments = 0;
        $likedCount = 0;
        $failedCount = 0;
        $lastErr = null;

        foreach ($boosters as $idx => $bAcc) {
            $token = (string)$bAcc['access_token'];
            $plat = (string)$bAcc['platform'];
            $bAccId = (int)$bAcc['id'];
            $bClientId = (int)$bAcc['client_id'];

            $commentText = $commentsList[$idx % count($commentsList)];
            $cSuccess = false;
            $cId = null;
            $cErr = null;

            if ($plat === 'facebook') {
                $cRes = MetaGraph::commentOnFacebookPost($targetPostId, $token, $commentText);
                if ($cRes['success']) {
                    $cSuccess = true;
                    $cId = (string)($cRes['comment_id'] ?? '');
                    $boostedComments++;
                } else {
                    $cErr = (string)($cRes['error'] ?? 'Comment failed');
                    $lastErr = $cErr;
                    $failedCount++;
                }

                if ($doLike) {
                    $lRes = MetaGraph::likeFacebookPost($targetPostId, $token);
                    if ($lRes['success']) {
                        $likedCount++;
                    }
                }
            } elseif ($plat === 'instagram') {
                $cRes = MetaGraph::commentOnInstagramMedia($targetPostId, $token, $commentText);
                if ($cRes['success']) {
                    $cSuccess = true;
                    $cId = (string)($cRes['comment_id'] ?? '');
                    $boostedComments++;
                } else {
                    $cErr = (string)($cRes['error'] ?? 'IG Comment failed');
                    $lastErr = $cErr;
                    $failedCount++;
                }
            } elseif ($plat === 'linkedin') {
                $actorUrn = (string)($bAcc['linkedin_urn'] ?? '');
                if (!empty($actorUrn)) {
                    $cRes = LinkedInClient::commentOnPost($targetPostId, $actorUrn, $token, $commentText);
                    if ($cRes['success']) {
                        $cSuccess = true;
                        $cId = (string)($cRes['comment_id'] ?? '');
                        $boostedComments++;
                    } else {
                        $cErr = (string)($cRes['error'] ?? 'LinkedIn Comment failed');
                        $lastErr = $cErr;
                        $failedCount++;
                    }
                }
            }

            // Record comment in database
            $pdo->prepare("
                INSERT INTO comments(client_id, account_id, platform, post_external_id, comment_text, response_id, status, error_message, created_at)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $bClientId,
                $bAccId,
                $plat,
                $targetPostId,
                $commentText,
                $cId,
                $cSuccess ? 'published' : 'failed',
                $cErr,
                now()
            ]);
        }

        $activeId = (int)($_SESSION['active_client_id'] ?? 2);
        $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
            ->execute([$activeId, "Cross-Account Boost executed on Post #{$targetPostId}: {$boostedComments} comments & {$likedCount} likes dropped", now()]);

        if ($boostedComments > 0) {
            flash('success', "🚀 Auto-Boost Successful! {$boostedComments} page(s) posted comments" . ($likedCount > 0 ? " and {$likedCount} page(s) liked" : "") . " the WSA Post (#{$targetPostId})!" . ($failedCount > 0 ? " ({$failedCount} deferred)" : ""));
        } else {
            flash('error', "Boost attempt encountered an issue: " . ($lastErr ?? 'Please check Post ID and page tokens.'));
        }

        redirect('index.php?page=comments');
    }

    if ($page === 'campaign-add') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $publishMode = (string)($_POST['publish_mode'] ?? 'instant');
        $autoBoost = !empty($_POST['auto_boost']) ? 1 : 0;
        $approvalStatus = 'approved';

        // WhatsApp CTA Handling
        $whatsappNumber = trim((string)($_POST['whatsapp_number'] ?? ''));
        $whatsappMsg = trim((string)($_POST['whatsapp_msg'] ?? ''));
        $enableWhatsapp = !empty($_POST['enable_whatsapp']) || !empty($whatsappNumber);

        if ($enableWhatsapp && !empty($whatsappNumber)) {
            $cleanNumber = preg_replace('/[^0-9]/', '', $whatsappNumber);
            if (!empty($cleanNumber)) {
                $waUrl = 'https://wa.me/' . $cleanNumber;
                if (!empty($whatsappMsg)) {
                    $waUrl .= '?text=' . urlencode($whatsappMsg);
                }
                if ($linkUrl === '') {
                    $linkUrl = $waUrl;
                }
                if (stripos($message, 'wa.me') === false) {
                    $message .= "\n\n📲 Chat on WhatsApp: " . $waUrl;
                }
            }
        }

        if ($publishMode === 'instant') {
            $scheduledFor = now();
        } else {
            $rawScheduled = (string)($_POST['scheduled_for'] ?? '');
            $schedDate = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $rawScheduled);
            if (!$schedDate) {
                $schedDate = DateTimeImmutable::createFromFormat('Y-m-d H:i', $rawScheduled);
            }
            $scheduledFor = $schedDate ? $schedDate->format('Y-m-d H:i:00') : date('Y-m-d H:i:00', time() + 3600);
        }

        if ($linkUrl !== '' && !filter_var($linkUrl, FILTER_VALIDATE_URL)) {
            flash('error', 'The target URL format is invalid. Must start with http:// or https://');
            redirect('index.php?page=campaigns');
        }

        $selectedPlatforms = array_values(array_intersect(array_keys(platforms()), (array)($_POST['platforms'] ?? [])));
        $selectedAccountIds = array_filter(array_map('intval', (array)($_POST['account_ids'] ?? [])));

        if (empty($selectedPlatforms) && empty($selectedAccountIds)) {
            flash('error', 'Please select at least one social page/account profile or platform for this campaign.');
            redirect('index.php?page=campaigns');
        }

        // Media Upload Handling
        $mediaPath = null;
        if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['media']['tmp_name'];
            $size = (int)$_FILES['media']['size'];
            if ($size > 5 * 1024 * 1024) {
                flash('error', 'Uploaded media file exceeds 5MB limit.');
                redirect('index.php?page=campaigns');
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif'
            ];
            if (!isset($allowed[$mime])) {
                flash('error', 'Invalid media format. Allowed types: JPG, PNG, WEBP, GIF.');
                redirect('index.php?page=campaigns');
            }
            $ext = $allowed[$mime];
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($tmp, $dest)) {
                $mediaPath = 'uploads/' . $filename;
            } else {
                flash('error', 'Failed to store uploaded media file.');
                redirect('index.php?page=campaigns');
            }
        }

        // Atomic Transaction for Campaign + Job Batch Creation
        $pdo->beginTransaction();
        try {
            // Fetch selected target accounts
            $targetAccounts = [];
            if (!empty($selectedAccountIds)) {
                $inPlaceholders = implode(',', array_fill(0, count($selectedAccountIds), '?'));
                $accStmt = $pdo->prepare("SELECT * FROM accounts WHERE id IN ($inPlaceholders) AND client_id = ?");
                $accParams = array_merge($selectedAccountIds, [$clientId]);
                $accStmt->execute($accParams);
                $targetAccounts = $accStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Aggregate all platforms covered
            $allPlatforms = $selectedPlatforms;
            foreach ($targetAccounts as $ta) {
                if (!in_array($ta['platform'], $allPlatforms, true)) {
                    $allPlatforms[] = $ta['platform'];
                }
            }

            $st = $pdo->prepare("
                INSERT INTO campaigns(client_id,name,message,link_url,media_path,platforms,target_accounts,scheduled_for,approval_status,status,auto_boost,created_at) 
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $st->execute([
                $clientId, $name, $message, $linkUrl, $mediaPath, 
                json_encode($allPlatforms, JSON_THROW_ON_ERROR), 
                json_encode(array_column($targetAccounts, 'id'), JSON_THROW_ON_ERROR), 
                $scheduledFor, $approvalStatus, 'scheduled', $autoBoost, now()
            ]);
            $cid = (int)$pdo->lastInsertId();

            $j = $pdo->prepare("
                INSERT INTO jobs(campaign_id,client_id,platform,account_id,status,execution_mode,scheduled_for,created_at) 
                VALUES(?,?,?,?,?,?,?,?)
            ");

            $totalJobsCreated = 0;
            $platformsWithAccounts = [];

            // 1. Create jobs for specifically selected accounts / pages
            foreach ($targetAccounts as $ta) {
                $p = $ta['platform'];
                $platformsWithAccounts[$p] = true;
                $isManual = in_array($p, ['medium', 'behance', 'imgur', 'deviantart'], true);
                $mode = $isManual ? 'manual' : 'automated';
                $status = $isManual ? 'manual_pending' : 'queued';
                $j->execute([$cid, $clientId, $p, (int)$ta['id'], $status, $mode, $scheduledFor, now()]);
                $totalJobsCreated++;
            }

            // 2. Create generic platform jobs for any checked platform that didn't have specific account selected
            foreach ($selectedPlatforms as $p) {
                if (!isset($platformsWithAccounts[$p])) {
                    $isManual = in_array($p, ['medium', 'behance', 'imgur', 'deviantart'], true);
                    $mode = $isManual ? 'manual' : 'automated';
                    $status = $isManual ? 'manual_pending' : 'queued';
                    $j->execute([$cid, $clientId, $p, null, $status, $mode, $scheduledFor, now()]);
                    $totalJobsCreated++;
                }
            }

            $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                ->execute([$clientId, "Campaign scheduled: {$name} across {$totalJobsCreated} targeted page/channel job(s)", 'success', now()]);

            $pdo->commit();

            if ($publishMode === 'instant') {
                $qRes = QueueEngine::process(25);
                $pubCount = (int)($qRes['published'] ?? 0);
                $failCount = (int)($qRes['failed'] ?? 0);
                $errList = $qRes['errors'] ?? [];

                if ($pubCount > 0) {
                    flash('success', "🚀 Post published LIVE successfully to {$pubCount} channel(s)!" . ($failCount > 0 ? " ({$failCount} deferred or requires manual action)" : ''));
                } elseif ($failCount > 0) {
                    $firstMsg = (string)($errList[0]['message'] ?? '');
                    if (stripos($firstMsg, 'pages_manage_posts') !== false || stripos($firstMsg, '(#200)') !== false) {
                        flash('error', "⚠️ Post ban gayi hai, lekin Facebook ne block kiya: Page par live post publish karne ke liye 'pages_manage_posts' permission chahiye hoti hai. Kripya <b>Accounts</b> menu me jakar 'Full Automation' select karein aur <b>Connect Meta</b> par click karke permissions approve karein.");
                    } else {
                        flash('error', "Post created, but immediate delivery had an issue on {$failCount} channel(s): " . h($firstMsg ?: 'Check Manual Queue or API status.'));
                    }
                } else {
                    flash('success', "Post queued and ready for automatic dispatch.");
                }
            } else {
                flash('success', "Campaign scheduled successfully with {$totalJobsCreated} targeted channel job(s).");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Database transaction error: ' . $e->getMessage());
        }
        redirect('index.php?page=campaigns');
    }

    if ($page === 'campaign-cancel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->beginTransaction();
            try {
                $cSt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
                $cSt->execute([$id]);
                $cData = $cSt->fetch(PDO::FETCH_ASSOC);
                if ($cData && $cData['status'] === 'scheduled') {
                    $pdo->prepare("UPDATE campaigns SET status = 'cancelled' WHERE id = ?")->execute([$id]);
                    $pdo->prepare("UPDATE jobs SET status = 'cancelled' WHERE campaign_id = ? AND status IN ('queued', 'manual_pending')")->execute([$id]);
                    $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                        ->execute([$cData['client_id'], 'Campaign cancelled: ' . $cData['name'], 'info', now()]);
                    $pdo->commit();
                    flash('success', 'Campaign and pending jobs successfully cancelled.');
                } else {
                    $pdo->rollBack();
                    flash('error', 'Campaign cannot be cancelled or was not found.');
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', 'Failed to cancel campaign: ' . $e->getMessage());
            }
        }
        redirect('index.php?page=campaigns');
    }

    if ($page === 'campaign-retry') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE jobs SET status = 'queued', error_message = NULL, scheduled_for = ? WHERE campaign_id = ? AND status = 'failed'")
                ->execute([now(), $id]);
            $pdo->prepare("UPDATE campaigns SET status = 'scheduled' WHERE id = ?")->execute([$id]);
            $qRes = QueueEngine::process(25);
            $pubCount = (int)($qRes['published'] ?? 0);
            $failCount = (int)($qRes['failed'] ?? 0);
            $errList = $qRes['errors'] ?? [];
            if ($pubCount > 0) {
                flash('success', "🚀 Retry successful! Post published LIVE to {$pubCount} channel(s)!");
            } elseif ($failCount > 0) {
                $firstMsg = (string)($errList[0]['message'] ?? '');
                if (stripos($firstMsg, 'pages_manage_posts') !== false || stripos($firstMsg, '(#200)') !== false) {
                    flash('error', "⚠️ Facebook Permission abhi bhi missing hai ('pages_manage_posts'). Pehle upar '1-Click Reconnect Meta' button par click karke Facebook login complete karein.");
                } else {
                    flash('error', "Retry failed: " . h($firstMsg));
                }
            } else {
                flash('info', "No failed jobs found to retry.");
            }
        }
        redirect('index.php?page=campaigns');
    }

    if ($page === 'run-queue') {
        $res = QueueEngine::process(50);
        $ret = trim((string)($_POST['return'] ?? ''));
        $targetUrl = 'index.php' . ($ret && $ret !== 'dashboard' ? '?page=' . urlencode($ret) : '');

        $pub = (int)($res['published'] ?? 0);
        $fail = (int)($res['failed'] ?? 0);
        if ($pub > 0) {
            flash('success', "⚡ Queue Synced: {$pub} scheduled post(s) successfully published live!" . ($fail > 0 ? " ({$fail} deferred/failed)" : ''));
        } elseif ($fail > 0) {
            flash('error', "⚠️ Queue checked: {$fail} job(s) encountered an issue. Check Channel Matrix or API permissions.");
        } else {
            flash('success', "⚡ AI Engine active & synced! All posts are up-to-date (0 pending scheduled posts due right now).");
        }
        redirect($targetUrl);
    }

    if ($page === 'job-complete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $job = $pdo->prepare("SELECT j.*, c.name campaign_name FROM jobs j JOIN campaigns c ON c.id=j.campaign_id WHERE j.id = ?");
            $job->execute([$id]);
            $jData = $job->fetch(PDO::FETCH_ASSOC);
            if ($jData) {
                $pdo->prepare("UPDATE jobs SET status='published' WHERE id=?")->execute([$id]);
                $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
                    ->execute([(int)$jData['client_id'], 'Job published on ' . (platforms()[$jData['platform']][0] ?? $jData['platform']) . ' for ' . $jData['campaign_name'], 'success', now()]);
                flash('success', 'Job marked published.');
            } else {
                flash('error', 'Job not found.');
            }
        }
        redirect('index.php?page=manual');
    }

    if ($page === 'password-change') {
        $curr = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters long.');
            redirect('index.php?page=settings');
        }
        if ($new !== $confirm) {
            flash('error', 'New password and confirmation do not match.');
            redirect('index.php?page=settings');
        }
        $adminSt = $pdo->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $adminSt->execute([(int)$_SESSION['admin_id']]);
        $a = $adminSt->fetch(PDO::FETCH_ASSOC);
        if (!$a || !password_verify($curr, $a['password_hash'])) {
            flash('error', 'Current password is incorrect.');
            redirect('index.php?page=settings');
        }
        $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['admin_id']]);
        $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
            ->execute([null, 'Administrator password changed', 'success', now()]);
        flash('success', 'Password successfully updated.');
        redirect('index.php?page=settings');
    }

    // ------------------------------------------------------------------
    // Group Distribution & Multi-Profile Rotation Actions (POST)
    // ------------------------------------------------------------------
    if ($page === 'sender-profile-add') {
        $cId = (int)($activeClientId ?: 2);
        $addMode = (string)($_POST['add_mode'] ?? 'existing_account');
        $limit = max(5, min(50, (int)($_POST['daily_limit'] ?? 20)));

        if ($addMode === 'existing_account') {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND platform = 'facebook'");
            $stmt->execute([$accountId]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$acc) {
                flash('error', 'Please select a valid connected Facebook Account / Page.');
                redirect('index.php?page=groups');
            }

            $name = (string)$acc['label'];
            $fbId = (string)($acc['external_id'] ?? '');
            $profUrl = 'https://www.facebook.com/' . $fbId;
            $notes = 'Real Connected Meta Account';

            $pdo->prepare("
                INSERT INTO sender_profiles(client_id, name, profile_url, fb_user_id, is_enabled, daily_limit, shares_today, status, notes, created_at)
                VALUES(?, ?, ?, ?, 1, ?, 0, 'active', ?, datetime('now'))
            ")->execute([$cId, $name, $profUrl, $fbId, $limit, $notes]);

            flash('success', "✅ Shaandar! Real Connected Account '{$name}' successfully linked to Group Distribution Hub!");
            redirect('index.php?page=groups');
        } elseif ($addMode === 'meta_token') {
            $rawToken = trim((string)($_POST['meta_user_token'] ?? ''));
            if ($rawToken === '') {
                flash('error', 'Please enter a valid Meta User Access Token.');
                redirect('index.php?page=groups');
            }

            $userRes = MetaGraph::inspectUserProfile($rawToken);
            if (!$userRes['success']) {
                flash('error', 'Meta Graph API Error: ' . ($userRes['error'] ?? 'Invalid token'));
                redirect('index.php?page=groups');
            }

            $name = $userRes['name'];
            $fbId = $userRes['id'];
            $profUrl = 'https://www.facebook.com/' . $fbId;
            $avatarUrl = $userRes['avatar_url'];
            $notes = 'Live Verified Meta OAuth ID';

            $pdo->prepare("
                INSERT INTO sender_profiles(client_id, name, profile_url, fb_user_id, avatar_url, is_enabled, daily_limit, shares_today, status, notes, created_at)
                VALUES(?, ?, ?, ?, ?, 1, ?, 0, 'active', ?, datetime('now'))
            ")->execute([$cId, $name, $profUrl, $fbId, $avatarUrl, $limit, $notes]);

            flash('success', "🚀 100% Real-Time Verified! Facebook Profile '{$name}' (ID: {$fbId}) successfully connected via Meta Graph API!");
            redirect('index.php?page=groups');
        } else {
            // Browser Sharer (Manual Team Member)
            $name = trim((string)($_POST['name'] ?? ''));
            $profUrl = trim((string)($_POST['profile_url'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? 'Team Browser Sharer'));

            if ($name === '') {
                flash('error', 'Team member profile name cannot be empty.');
                redirect('index.php?page=groups');
            }

            $pdo->prepare("
                INSERT INTO sender_profiles(client_id, name, profile_url, is_enabled, daily_limit, shares_today, status, notes, created_at)
                VALUES(?, ?, ?, 1, ?, 0, 'active', ?, datetime('now'))
            ")->execute([$cId, $name, $profUrl, $limit, $notes]);

            flash('success', "Team browser sharer '{$name}' added to distribution rotation.");
            redirect('index.php?page=groups');
        }
    }

    if ($page === 'sender-profile-toggle') {
        $profId = (int)($_POST['profile_id'] ?? 0);
        $st = $pdo->prepare("SELECT id, name, is_enabled FROM sender_profiles WHERE id = ?");
        $st->execute([$profId]);
        $prof = $st->fetch(PDO::FETCH_ASSOC);

        if ($prof) {
            $newStatus = (int)$prof['is_enabled'] === 1 ? 0 : 1;
            $pdo->prepare("UPDATE sender_profiles SET is_enabled = ? WHERE id = ?")->execute([$newStatus, $profId]);
            $statusText = $newStatus === 1 ? 'activated in rotation' : 'excluded (paused)';
            flash('success', "Profile '{$prof['name']}' is now {$statusText}.");
        }
        redirect('index.php?page=groups');
    }

    if ($page === 'sender-profile-delete') {
        $profId = (int)($_POST['profile_id'] ?? 0);
        $pdo->prepare("DELETE FROM sender_profiles WHERE id = ?")->execute([$profId]);
        flash('success', 'Sender profile removed.');
        redirect('index.php?page=groups');
    }

    if ($page === 'group-target-add') {
        $cId = (int)($activeClientId ?: 2);
        $name = trim((string)($_POST['name'] ?? ''));
        $grpUrl = trim((string)($_POST['group_url'] ?? ''));
        $niche = trim((string)($_POST['niche_category'] ?? 'General'));
        $members = trim((string)($_POST['member_count'] ?? 'Active'));
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;

        if ($name === '' || $grpUrl === '') {
            flash('error', 'Group name and group link are required.');
            redirect('index.php?page=groups');
        }

        // Clean & normalize URL
        if (!preg_match('~^https?://~i', $grpUrl)) {
            if (preg_match('~facebook\.com/groups/~i', $grpUrl)) {
                $grpUrl = 'https://' . ltrim($grpUrl, '/');
            } else {
                $grpUrl = 'https://www.facebook.com/groups/' . trim($grpUrl, '/');
            }
        }
        $grpUrl = preg_replace('/\?.*$/', '', $grpUrl);

        // Prevent duplicate
        $chk = $pdo->prepare("SELECT id FROM group_targets WHERE client_id = ? AND group_url = ?");
        $chk->execute([$cId, $grpUrl]);
        if ($chk->fetchColumn()) {
            flash('info', "Group '{$name}' is already in your verified pool.");
            redirect('index.php?page=groups');
        }

        $pdo->prepare("
            INSERT INTO group_targets(client_id, account_id, name, group_url, niche_category, member_count, is_enabled, created_at)
            VALUES(?, ?, ?, ?, ?, ?, 1, datetime('now'))
        ")->execute([$cId, $accountId, $name, $grpUrl, $niche, $members]);

        $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'info', ?)")
            ->execute([$cId, "Target Facebook group added: {$name}", now()]);

        flash('success', "Target group '{$name}' added to pool.");
        redirect('index.php?page=groups' . ($accountId ? '&account_id=' . $accountId : ''));
    }

    if ($page === 'group-target-bulk-add') {
        $cId = (int)($activeClientId ?: 2);
        $rawText = trim((string)($_POST['bulk_groups'] ?? ''));
        $defaultNiche = trim((string)($_POST['default_niche'] ?? 'General'));
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
        $lines = [];

        // Check if CSV file was uploaded
        if (isset($_FILES['csv_file']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle !== false) {
                $isHeader = true;
                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) continue;
                    
                    // Skip header row if it contains words like 'name' or 'url' or 'group'
                    $firstCol = strtolower(trim((string)$data[0]));
                    if ($isHeader && (strpos($firstCol, 'name') !== false || strpos($firstCol, 'url') !== false || strpos($firstCol, 'group') !== false)) {
                        $isHeader = false;
                        continue;
                    }
                    $isHeader = false;

                    // If column 1 is group name and column 2 is URL
                    if (isset($data[1]) && trim((string)$data[1]) !== '') {
                        $lines[] = trim((string)$data[0]) . ',' . trim((string)$data[1]);
                    } else {
                        $lines[] = trim((string)$data[0]);
                    }
                }
                fclose($handle);
            }
        } elseif ($rawText !== '') {
            $lines = preg_split('/[\r\n]+/', $rawText);
        }

        if (empty($lines)) {
            flash('error', 'Kripya CSV file upload karein ya text box me group links daalein.');
            redirect('index.php?page=groups');
        }

        $addedCount = 0;
        $skippedCount = 0;
        $ins = $pdo->prepare("
            INSERT INTO group_targets(client_id, account_id, name, group_url, niche_category, member_count, is_enabled, created_at)
            VALUES(?, ?, ?, ?, ?, 'Active', 1, datetime('now'))
        ");
        $chkDup = $pdo->prepare("SELECT id FROM group_targets WHERE client_id = ? AND group_url = ?");

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $groupName = '';
            $groupUrl = '';

            // Format 1: "Group Name, https://facebook.com/groups/..." OR "Group Name | https://..."
            if (strpos($line, ',') !== false) {
                $parts = explode(',', $line, 2);
                $groupName = trim($parts[0]);
                $groupUrl = trim($parts[1]);
            } elseif (strpos($line, '|') !== false) {
                $parts = explode('|', $line, 2);
                $groupName = trim($parts[0]);
                $groupUrl = trim($parts[1]);
            } else {
                $groupUrl = $line;
            }

            // Clean & validate URL
            if (!preg_match('~^https?://~i', $groupUrl)) {
                if (preg_match('~facebook\.com/groups/~i', $groupUrl)) {
                    $groupUrl = 'https://' . ltrim($groupUrl, '/');
                } else {
                    $groupUrl = 'https://www.facebook.com/groups/' . trim($groupUrl, '/');
                }
            }
            $groupUrl = preg_replace('/\?.*$/', '', $groupUrl);

            // Deduplication
            $chkDup->execute([$cId, $groupUrl]);
            if ($chkDup->fetchColumn()) {
                $skippedCount++;
                continue;
            }

            // Extract readable group name if not specified
            if ($groupName === '') {
                if (preg_match('~/groups/([^/?#]+)~i', $groupUrl, $m)) {
                    $groupName = ucwords(str_replace(['-', '_', '.'], ' ', $m[1]));
                } else {
                    $groupName = 'Facebook Group #' . ($addedCount + 1);
                }
            }

            $ins->execute([$cId, $accountId, $groupName, $groupUrl, $defaultNiche]);
            $addedCount++;
        }

        if ($addedCount > 0) {
            $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
                ->execute([$cId, "Bulk imported {$addedCount} Facebook target group(s)", now()]);
        }

        $msg = "🚀 Shaandar! {$addedCount} Facebook Groups successfully pool me add ho gaye!";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} duplicate pehle se pool me the).";
        }
        flash('success', $msg);
        redirect('index.php?page=groups' . ($accountId ? '&account_id=' . $accountId : ''));
    }

    if ($page === 'group-target-toggle') {
        $grpId = (int)($_POST['group_id'] ?? 0);
        $st = $pdo->prepare("SELECT id, name, is_enabled FROM group_targets WHERE id = ?");
        $st->execute([$grpId]);
        $grp = $st->fetch(PDO::FETCH_ASSOC);

        if ($grp) {
            $newStatus = (int)$grp['is_enabled'] === 1 ? 0 : 1;
            $pdo->prepare("UPDATE group_targets SET is_enabled = ? WHERE id = ?")->execute([$newStatus, $grpId]);
            flash('success', "Group '{$grp['name']}' updated.");
        }
        redirect('index.php?page=groups');
    }

    if ($page === 'group-target-delete') {
        $grpId = (int)($_POST['group_id'] ?? 0);
        $pdo->prepare("DELETE FROM group_targets WHERE id = ?")->execute([$grpId]);
        flash('success', 'Group removed from pool.');
        redirect('index.php?page=groups');
    }

    if ($page === 'group-targets-clear-all') {
        $cId = (int)($activeClientId ?: 2);
        $pdo->prepare("DELETE FROM group_share_queue WHERE client_id = ?")->execute([$cId]);
        $pdo->prepare("DELETE FROM group_targets WHERE client_id = ?")->execute([$cId]);
        flash('success', 'Sabhi target groups successfully remove kar diye gaye hain.');
        redirect('index.php?page=groups');
    }

    if ($page === 'sync-fb-profile-groups') {
        $cId = (int)($activeClientId ?: 2);
        $syncMode = (string)($_POST['sync_mode'] ?? 'paste');
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
        $defaultNiche = trim((string)($_POST['default_niche'] ?? 'General'));

        if ($syncMode === 'api') {
            // Attempt Meta Graph API Sync
            $tokenToUse = '';
            if (!empty($_POST['custom_token'])) {
                $tokenToUse = trim((string)$_POST['custom_token']);
            } elseif ($accountId) {
                $st = $pdo->prepare("SELECT access_token FROM accounts WHERE id = ?");
                $st->execute([$accountId]);
                $tokenToUse = (string)$st->fetchColumn();
            }

            if (empty($tokenToUse)) {
                flash('error', 'No Meta Access Token available for API sync.');
                redirect('index.php?page=groups');
            }

            $inspect = MetaGraph::inspectTokenScopes($pdo, $tokenToUse);
            $userId = $inspect['user_id'] ?? 'me';

            $apiRes = MetaGraph::fetchUserGroups($tokenToUse, $userId);
            if (!$apiRes['success']) {
                flash('error', 'Meta API Notice: ' . ($apiRes['error'] ?? 'Graph API request failed.'));
                redirect('index.php?page=groups');
            }

            $groups = $apiRes['groups'] ?? [];
            if (empty($groups)) {
                flash('info', 'Meta Graph API se 0 groups mile (Meta v19+ policy ke tahat member groups API se blocked hain). Kripya "⚡ Fast Paste / Auto-Extract" tab use karein jo aapke saare 100% joined groups load kar dega.');
                redirect('index.php?page=groups');
            }

            $added = 0;
            $ins = $pdo->prepare("INSERT INTO group_targets(client_id, account_id, name, group_url, niche_category, member_count, is_enabled, created_at) VALUES(?, ?, ?, ?, ?, ?, 1, datetime('now'))");
            $chk = $pdo->prepare("SELECT id FROM group_targets WHERE client_id = ? AND group_url = ?");

            foreach ($groups as $g) {
                $gId = (string)($g['id'] ?? '');
                $gName = (string)($g['name'] ?? 'Facebook Group');
                $gUrl = 'https://www.facebook.com/groups/' . $gId;
                $mCount = !empty($g['member_count']) ? ($g['member_count'] . ' members') : 'Active';

                $chk->execute([$cId, $gUrl]);
                if (!$chk->fetchColumn()) {
                    $ins->execute([$cId, $accountId, $gName, $gUrl, $defaultNiche, $mCount]);
                    $added++;
                }
            }

            flash('success', "🚀 Meta API se {$added} Facebook Groups successfully sync ho gaye!");
            redirect('index.php?page=groups' . ($accountId ? '&account_id=' . $accountId : ''));
        }

        // Paste / Extract Script / File Upload Mode
        $raw = '';
        if (isset($_FILES['html_file']) && is_uploaded_file($_FILES['html_file']['tmp_name'])) {
            $raw = (string)file_get_contents($_FILES['html_file']['tmp_name']);
        } elseif (!empty($_POST['raw_groups_data'])) {
            $raw = trim((string)$_POST['raw_groups_data']);
        }

        if ($raw === '') {
            flash('error', 'Kripya groups ke links, text, ya HTML paste karein ya file upload karein.');
            redirect('index.php?page=groups');
        }

        $imported = [];

        // 1. Try HTML <a> tag extraction
        if (preg_match_all('~<a[^>]*href=[\"\'](?:https?://)?(?:www\.)?facebook\.com/groups/([a-zA-Z0-9\.\_\-]+)[^\"\']*[\"\'][^>]*>(.*?)</a>~is', $raw, $mHtml, PREG_SET_ORDER)) {
            foreach ($mHtml as $m) {
                $slug = trim($m[1]);
                if (in_array(strtolower($slug), ['feed', 'joins', 'discover', 'create', 'search', 'category'], true)) continue;
                $cleanName = trim(strip_tags($m[2]));
                if ($cleanName === '' || strlen($cleanName) > 100) {
                    $cleanName = ucwords(str_replace(['-', '_', '.'], ' ', $slug));
                }
                $imported['https://www.facebook.com/groups/' . $slug] = $cleanName;
            }
        }

        // 2. Try line by line extraction
        $lines = preg_split('/[\r\n]+/', $raw);
        $prevLineText = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('~facebook\.com/groups/([a-zA-Z0-9\.\_\-]+)~i', $line, $mUrl)) {
                $slug = trim($mUrl[1]);
                if (in_array(strtolower($slug), ['feed', 'joins', 'discover', 'create', 'search', 'category'], true)) continue;
                $url = 'https://www.facebook.com/groups/' . $slug;

                if (!isset($imported[$url])) {
                    $gName = '';
                    if (strpos($line, '|') !== false) {
                        $parts = explode('|', $line, 2);
                        $gName = trim($parts[0]);
                    } elseif (strpos($line, ',') !== false) {
                        $parts = explode(',', $line, 2);
                        $gName = trim($parts[0]);
                    } elseif ($prevLineText !== '' && !preg_match('~https?://~i', $prevLineText) && strlen($prevLineText) < 80) {
                        $gName = $prevLineText;
                    }

                    if ($gName === '' || preg_match('~https?://~i', $gName)) {
                        $gName = ucwords(str_replace(['-', '_', '.'], ' ', $slug));
                    }

                    $imported[$url] = $gName;
                }
            } else {
                $prevLineText = $line;
            }
        }

        if (empty($imported)) {
            flash('error', 'Koi valid Facebook Group link nahi mila. Format: https://www.facebook.com/groups/...');
            redirect('index.php?page=groups');
        }

        $ins = $pdo->prepare("INSERT INTO group_targets(client_id, account_id, name, group_url, niche_category, member_count, is_enabled, created_at) VALUES(?, ?, ?, ?, ?, 'Active', 1, datetime('now'))");
        $chk = $pdo->prepare("SELECT id FROM group_targets WHERE client_id = ? AND group_url = ?");
        $added = 0;
        $duplicates = 0;

        foreach ($imported as $url => $name) {
            $chk->execute([$cId, $url]);
            if ($chk->fetchColumn()) {
                $duplicates++;
                continue;
            }
            $ins->execute([$cId, $accountId, $name, $url, $defaultNiche]);
            $added++;
        }

        $msg = "🚀 Shaandar! Facebook Profile ke {$added} groups successfully import ho gaye!";
        if ($duplicates > 0) {
            $msg .= " ({$duplicates} groups pehle se added the).";
        }
        flash('success', $msg);
        redirect('index.php?page=groups' . ($accountId ? '&account_id=' . $accountId : ''));
    }

    if ($page === 'group-queue-generate') {
        $cId = (int)($activeClientId ?: 2);
        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $selectedPost = trim((string)($_POST['selected_post_url'] ?? ''));
        if (str_starts_with($selectedPost, 'campaign_')) {
            $campaignId = (int)str_replace('campaign_', '', $selectedPost);
        }
        $nicheCategory = trim((string)($_POST['niche_category'] ?? 'all'));
        $targetShares = max(1, min(100, (int)($_POST['target_shares'] ?? 100)));
        $intervalMins = max(1, min(120, (int)($_POST['interval_minutes'] ?? 2)));
        $customCaption = trim((string)($_POST['caption'] ?? ''));

        $customPostUrl = trim((string)($_POST['custom_post_url'] ?? ''));
        if ($customPostUrl === '' && $selectedPost !== '' && $selectedPost !== 'custom' && !str_starts_with($selectedPost, 'campaign_')) {
            $customPostUrl = $selectedPost;
        }

        // Retrieve campaign info
        $cmpSt = $pdo->prepare("
            SELECT c.*, j.response_id
            FROM campaigns c
            LEFT JOIN jobs j ON j.campaign_id = c.id AND j.platform = 'facebook' AND j.status = 'published'
            WHERE c.id = ?
        ");
        $cmpSt->execute([$campaignId]);
        $cmp = $cmpSt->fetch(PDO::FETCH_ASSOC);

        // Determine post URL to share
        $postUrl = '';
        if ($customPostUrl !== '') {
            $postUrl = $customPostUrl;
        } elseif ($cmp && !empty($cmp['link_url'])) {
            $postUrl = $cmp['link_url'];
        } elseif ($cmp && !empty($cmp['response_id'])) {
            $respId = (string)$cmp['response_id'];
            if (strpos($respId, '_') !== false) {
                $parts = explode('_', $respId);
                $postUrl = 'https://www.facebook.com/' . $parts[0] . '/posts/' . $parts[1];
            } else {
                $postUrl = 'https://www.facebook.com/' . $respId;
            }
        }

        if ($postUrl === '') {
            $postUrl = 'https://www.facebook.com/573548745852007'; // Fallback to Official Page
        }

        // Selected Sender Pages & Shares Per Page (2-5 groups per page)
        $senderAccIds = array_filter(array_map('intval', (array)($_POST['sender_account_ids'] ?? [])));
        $sharesPerPage = max(1, min(10, (int)($_POST['shares_per_page'] ?? 3)));

        // If specific sender pages were selected, fetch their account details
        $selectedSenderAccounts = [];
        if (!empty($senderAccIds)) {
            $inClause = implode(',', array_fill(0, count($senderAccIds), '?'));
            $accSt = $pdo->prepare("SELECT id, label, external_id FROM accounts WHERE id IN ($inClause) ORDER BY id ASC");
            $accSt->execute($senderAccIds);
            $selectedSenderAccounts = $accSt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fallback: If no pages were explicitly checked, use all booster pages (not default main page)
        if (empty($selectedSenderAccounts)) {
            $mainId = (int)(get_system_setting('default_main_page_id', '24') ?: 24);
            $selectedSenderAccounts = $pdo->query("SELECT id, label, external_id FROM accounts WHERE platform = 'facebook' AND id != {$mainId} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($selectedSenderAccounts)) {
            $selectedSenderAccounts = $pdo->query("SELECT id, label, external_id FROM accounts WHERE platform = 'facebook' ORDER BY id ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        }

        $numSenders = max(1, count($selectedSenderAccounts));
        if (!empty($_POST['target_shares'])) {
            $totalTargetShares = $targetShares;
        } else {
            $totalTargetShares = $numSenders * $sharesPerPage;
        }

        // Group Selection: Manual Pick vs Smart Rotation (Har Baar Alag Group)
        $manualGroupIds = array_filter(array_map('intval', (array)($_POST['selected_group_ids'] ?? [])));
        $groupSelectionMode = trim((string)($_POST['group_selection_mode'] ?? 'auto'));

        if ($groupSelectionMode === 'manual' && !empty($manualGroupIds)) {
            // User explicitly chose these groups
            $inGrp = implode(',', array_fill(0, count($manualGroupIds), '?'));
            $grpSt = $pdo->prepare("SELECT * FROM group_targets WHERE id IN ($inGrp) AND is_enabled = 1 ORDER BY CASE WHEN last_shared_at IS NULL THEN 0 ELSE 1 END ASC, last_shared_at ASC, id ASC");
            $grpSt->execute($manualGroupIds);
            $groups = $grpSt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Mode A: Smart Distinct Rotation (Har Baar Alag Group)
            // 1. Exclude groups that are already sitting in 'queued' status right now!
            $grpSql = "SELECT * FROM group_targets WHERE client_id = ? AND is_enabled = 1";
            $grpParams = [$cId];
            if ($nicheCategory !== 'all') {
                $grpSql .= " AND niche_category = ?";
                $grpParams[] = $nicheCategory;
            }
            // EXCLUDE currently queued groups so each schedule run goes to different groups!
            $grpSql .= " AND id NOT IN (SELECT group_id FROM group_share_queue WHERE status = 'queued')";
            // Prioritize never shared, then least recently shared, with randomized tie-break
            $grpSql .= " ORDER BY CASE WHEN last_shared_at IS NULL THEN 0 ELSE 1 END ASC, last_shared_at ASC, RANDOM() LIMIT " . (int)$totalTargetShares;
            
            $grpSt = $pdo->prepare($grpSql);
            $grpSt->execute($grpParams);
            $groups = $grpSt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback if all groups are currently queued or client mismatch: pick least recently shared
            if (empty($groups)) {
                $grpSql = "SELECT * FROM group_targets WHERE is_enabled = 1";
                $grpParams = [];
                if ($nicheCategory !== 'all') {
                    $grpSql .= " AND niche_category = ?";
                    $grpParams[] = $nicheCategory;
                }
                $grpSql .= " ORDER BY last_shared_at ASC, RANDOM() LIMIT " . (int)$totalTargetShares;
                $grpSt = $pdo->prepare($grpSql);
                $grpSt->execute($grpParams);
                $groups = $grpSt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        if (empty($groups)) {
            flash('error', 'No target groups found. Please import or enable groups first.');
            redirect('index.php?page=groups');
        }

        // Generate Multi-Page distribution queue schedule
        $insQueue = $pdo->prepare("
            INSERT INTO group_share_queue(client_id, campaign_id, group_id, sender_account_id, sender_profile_id, post_url, caption, status, scheduled_for, created_at)
            VALUES(?, ?, ?, ?, NULL, ?, ?, 'queued', ?, datetime('now'))
        ");

        $nowTs = time() + 60; // First share starts in 1 minute
        $scheduledCount = 0;

        if ($groupSelectionMode === 'manual' && !empty($manualGroupIds)) {
            // Manual selection: Schedule EVERY selected group, distributing round-robin across selected sender pages!
            foreach ($groups as $idx => $targetGroup) {
                if ($scheduledCount >= $totalTargetShares) {
                    break;
                }
                $senderAcc = $selectedSenderAccounts[$idx % $numSenders];
                $scheduledTime = date('Y-m-d H:i:s', $nowTs + ($scheduledCount * $intervalMins * 60));

                $insQueue->execute([
                    $cId,
                    $campaignId ?: null,
                    $targetGroup['id'],
                    $senderAcc['id'],
                    $postUrl,
                    $customCaption ?: ($cmp['message'] ?? ''),
                    $scheduledTime,
                ]);

                $scheduledCount++;
            }
        } else {
            // Smart Rotation mode: Distribute $sharesPerPage distinct groups per page up to $totalTargetShares
            $groupIndex = 0;
            $totalAvailableGroups = count($groups);

            foreach ($selectedSenderAccounts as $senderAcc) {
                for ($s = 0; $s < $sharesPerPage; $s++) {
                    if ($groupIndex >= $totalAvailableGroups || $scheduledCount >= $totalTargetShares) {
                        break;
                    }
                    $targetGroup = $groups[$groupIndex];
                    $scheduledTime = date('Y-m-d H:i:s', $nowTs + ($scheduledCount * $intervalMins * 60));

                    $insQueue->execute([
                        $cId,
                        $campaignId ?: null,
                        $targetGroup['id'],
                        $senderAcc['id'],
                        $postUrl,
                        $customCaption ?: ($cmp['message'] ?? ''),
                        $scheduledTime,
                    ]);

                    $scheduledCount++;
                    $groupIndex++;
                }
                if ($scheduledCount >= $totalTargetShares) {
                    break;
                }
            }
        }

        $senderNames = implode(', ', array_slice(array_column($selectedSenderAccounts, 'label'), 0, 3));
        if (count($selectedSenderAccounts) > 3) {
            $senderNames .= ' aur ' . (count($selectedSenderAccounts) - 3) . ' anya pages';
        }

        $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'info', ?)")
            ->execute([$cId, "Generated multi-page group schedule: {$scheduledCount} shares across {$numSenders} pages", now()]);

        flash('success', "🚀 Scheduled {$scheduledCount} Group Shares! {$numSenders} Pages [{$senderNames}] ke zariye har page {$sharesPerPage} groups me share karega ({$intervalMins} min ke safe gap ke sath).");
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-queue-clear') {
        $cId = (int)($activeClientId ?: 2);
        $pdo->prepare("DELETE FROM group_share_queue WHERE client_id = ? AND status = 'queued'")->execute([$cId]);
        flash('success', 'Pending scheduled group shares cleared.');
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-item-mark-shared') {
        $itemId = (int)($_POST['queue_id'] ?? 0);
        $sharedPostUrl = trim((string)($_POST['shared_post_url'] ?? ''));
        $shareStatus = trim((string)($_POST['share_status'] ?? 'shared'));
        if (!in_array($shareStatus, ['shared', 'pending_approval'], true)) {
            $shareStatus = 'shared';
        }
        if ($itemId > 0) {
            $st = $pdo->prepare("SELECT q.*, sp.id sender_id, gt.id group_id, gt.name group_name, gt.group_url FROM group_share_queue q LEFT JOIN sender_profiles sp ON sp.id = q.sender_profile_id JOIN group_targets gt ON gt.id = q.group_id WHERE q.id = ?");
            $st->execute([$itemId]);
            $item = $st->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $now = now();
                $finalSharedUrl = $sharedPostUrl !== '' ? $sharedPostUrl : ($item['group_url'] ?? $item['post_url']);
                $pdo->prepare("UPDATE group_share_queue SET status = ?, shared_at = ?, shared_post_url = ? WHERE id = ?")
                    ->execute([$shareStatus, $now, $finalSharedUrl, $itemId]);
                
                // Update sender shares today
                if (!empty($item['sender_id']) && $shareStatus === 'shared') {
                    $pdo->prepare("UPDATE sender_profiles SET shares_today = shares_today + 1, last_share_at = ? WHERE id = ?")
                        ->execute([$now, (int)$item['sender_id']]);
                }
                // Update target group last share
                if (!empty($item['group_id'])) {
                    $pdo->prepare("UPDATE group_targets SET total_shares = total_shares + 1, last_shared_at = ? WHERE id = ?")
                        ->execute([$now, (int)$item['group_id']]);
                }

                $statusLabel = ($shareStatus === 'pending_approval') ? 'Pending Admin Approval' : 'Published Live';
                $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
                    ->execute([$item['client_id'], "Group share recorded as {$statusLabel} for {$item['group_name']}", now()]);

                if ($shareStatus === 'pending_approval') {
                    flash('success', "🟡 Post record ho gayi hai: 'Waiting for Admin Approval' for {$item['group_name']}. Admin ke approve karne par live link add kar sakte hain.");
                } else {
                    flash('success', "✅ Group Share recorded successfully! Status updated to Live Published.");
                }
            }
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-item-approve') {
        $itemId = (int)($_POST['queue_id'] ?? $_GET['queue_id'] ?? 0);
        $liveUrl = trim((string)($_POST['shared_post_url'] ?? ''));
        if ($itemId > 0) {
            $now = now();
            if ($liveUrl !== '') {
                $pdo->prepare("UPDATE group_share_queue SET status = 'shared', shared_at = ?, shared_post_url = ? WHERE id = ?")->execute([$now, $liveUrl, $itemId]);
            } else {
                $pdo->prepare("UPDATE group_share_queue SET status = 'shared', shared_at = ? WHERE id = ?")->execute([$now, $itemId]);
            }
            flash('success', '🎉 Post status updated to Live Published!');
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-item-delete') {
        $itemId = (int)($_POST['queue_id'] ?? 0);
        if ($itemId > 0) {
            $pdo->prepare("DELETE FROM group_share_queue WHERE id = ?")->execute([$itemId]);
            flash('success', 'Group share item deleted from queue.');
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-item-requeue') {
        $itemId = (int)($_POST['queue_id'] ?? $_GET['queue_id'] ?? 0);
        if ($itemId > 0) {
            $pdo->prepare("UPDATE group_share_queue SET status = 'queued', error_message = NULL, scheduled_for = ? WHERE id = ?")
                ->execute([now(), $itemId]);
            flash('success', '🔄 Item successfully re-queued! Auto-Pilot ise next share me try karega.');
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-queue-process-due') {
        $res = QueueEngine::processGroupShareQueue(25);
        if ($res['shared'] > 0) {
            flash('success', "⚡ Success! {$res['shared']} due group shares successfully API se publish ho gaye!");
        } else {
            flash('warning', "ℹ️ Meta (Facebook) policy third-party groups me direct API bot posting restrict karti hai. Niche table me '🚀 Share (Manual)' button dabayein — post URL turant copy ho kar group khul jayega aur aap wahan paste karke live link save kar sakte hain.");
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-item-mark-shared-fast') {
        $itemId = (int)($_POST['queue_id'] ?? $_GET['queue_id'] ?? 0);
        if ($itemId > 0) {
            $st = $pdo->prepare("SELECT q.*, gt.name group_name, gt.group_url FROM group_share_queue q JOIN group_targets gt ON gt.id = q.group_id WHERE q.id = ?");
            $st->execute([$itemId]);
            $item = $st->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $now = now();
                $finalSharedUrl = $item['group_url'] ?? $item['post_url'];
                $pdo->prepare("UPDATE group_share_queue SET status = 'shared', shared_at = ?, shared_post_url = ? WHERE id = ?")
                    ->execute([$now, $finalSharedUrl, $itemId]);
                $pdo->prepare("UPDATE group_targets SET total_shares = total_shares + 1, last_shared_at = ? WHERE id = ?")
                    ->execute([$now, (int)$item['group_id']]);
                
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['format']) && $_GET['format'] === 'json')) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
                flash('success', "✅ Group share marked published to {$item['group_name']}!");
            }
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-autopilot-run-now') {
        $cId = (int)($activeClientId ?: 2);
        $sharesCount = (int)(get_system_setting('group_autopilot_daily_count', '5') ?: 5);
        $intervalMins = (int)(get_system_setting('group_autopilot_interval_mins', '30') ?: 30);
        $targetAccId = (int)(get_system_setting('group_autopilot_account_id', '24') ?: 24);

        $res = QueueEngine::generateGroupAutoPilotQueue($cId, $targetAccId, $sharesCount, $intervalMins);
        if ($res['success']) {
            $todayDate = date('Y-m-d');
            set_system_setting('group_autopilot_last_run_date', $todayDate);
            set_system_setting('group_autopilot_last_post_url', $res['post_url'] ?? '');
            if (!empty($res['post'])) {
                set_system_setting('group_autopilot_cached_post', json_encode($res['post']));
            }
            $grpsList = implode(', ', array_slice($res['groups'] ?? [], 0, 3));
            if (count($res['groups'] ?? []) > 3) {
                $grpsList .= ' aur ' . (count($res['groups']) - 3) . ' anya groups';
            }
            flash('success', "🚀 Auto-Pilot Chalu! WSA Digital Agency ki latest reel/post aaj ke {$res['count']} groups me schedule ho gayi hai: [{$grpsList}] ({$intervalMins} min ke safe gap ke sath).");
        } else {
            flash('error', "Auto-Pilot Error: " . ($res['error'] ?? 'Kuch issue hua.'));
        }
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'group-autopilot-save-settings') {
        $enabled = isset($_POST['autopilot_enabled']) ? '1' : '0';
        $dailyCount = max(1, min(50, (int)($_POST['daily_count'] ?? 5)));
        $intervalMins = max(1, min(120, (int)($_POST['interval_mins'] ?? 2)));
        $accountId = (int)($_POST['account_id'] ?? 24);

        set_system_setting('group_autopilot_enabled', (string)$enabled);
        set_system_setting('group_autopilot_daily_count', (string)$dailyCount);
        set_system_setting('group_autopilot_interval_mins', (string)$intervalMins);
        set_system_setting('group_autopilot_account_id', (string)$accountId);

        flash('success', "⚙️ Auto-Pilot Settings save ho gayi hain! (Status: " . ($enabled === '1' ? 'Active' : 'Paused') . ", Daily: {$dailyCount} groups, Interval: {$intervalMins} mins)");
        redirect('index.php?page=groups&tab=queue');
    }

    if ($page === 'set-default-main-page') {
        $accId = (int)($_POST['main_page_account_id'] ?? 24);
        set_system_setting('default_main_page_id', (string)$accId);
        set_system_setting('group_autopilot_account_id', (string)$accId);
        set_system_setting('group_autopilot_cached_post', '');

        $st = $pdo->prepare("SELECT label FROM accounts WHERE id = ?");
        $st->execute([$accId]);
        $accLabel = $st->fetchColumn() ?: 'Selected Page';

        flash('success', "🎯 Default Main Page successfully set kar diya gaya: '{$accLabel}'. Ab iski 7-days posts aur reels auto-sync hongi.");
        redirect('index.php?page=groups');
    }

    if ($page === 'group-post-boost') {
        $cId = (int)($activeClientId ?: 2);
        $targetPostUrl = trim((string)($_POST['target_post_url'] ?? ''));
        $targetPostId = trim((string)($_POST['target_post_id'] ?? ''));
        $mainPageId = (int)(get_system_setting('default_main_page_id', '24') ?: 24);

        $cleanId = extract_social_post_id($targetPostUrl ?: $targetPostId);
        if (empty($cleanId)) {
            $cleanId = $targetPostId;
        }

        $boostRes = QueueEngine::dispatchAutoBoost($cId, $cleanId, $mainPageId);
        $pdo->prepare("INSERT INTO activity(client_id, message, status, created_at) VALUES(?, ?, 'success', ?)")
            ->execute([$cId, "Engagement Booster triggered for Post {$cleanId}", now()]);

        flash('success', "🚀 Auto-Engagement Triggered! Connected accounts se like aur supportive comments dispatch process shuru kar diya gaya.");
        redirect('index.php?page=groups');
    }
}

// ----------------------------------------------------------------------
// Meta OAuth 2.0 Integration Handlers (GET)
// ----------------------------------------------------------------------
if ($page === 'meta-connect') {
    $clientId = (int)($_GET['client_id'] ?? 0);
    $scopeMode = (string)($_GET['scope_mode'] ?? 'essential');
    if ($clientId <= 0) {
        flash('error', 'Please choose a client workspace to link Meta accounts to.');
        redirect('index.php?page=accounts');
    }

    if (!MetaGraph::isConfigured($pdo)) {
        flash('error', 'Meta App ID and Secret must be configured in Settings first.');
        redirect('index.php?page=settings');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['meta_oauth_state'] = $state;
    $_SESSION['meta_oauth_client_id'] = $clientId;

    $authUrl = MetaGraph::getAuthorizationUrl($pdo, $state, $scopeMode);
    if ($authUrl === '') {
        flash('error', 'Failed to generate Meta authorization URL.');
        redirect('index.php?page=accounts');
    }

    header('Location: ' . $authUrl);
    exit;
}

if ($page === 'meta-callback') {
    $code = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    $sessState = (string)($_SESSION['meta_oauth_state'] ?? '');
    $targetClientId = (int)($_SESSION['meta_oauth_client_id'] ?? 0);

    unset($_SESSION['meta_oauth_state'], $_SESSION['meta_oauth_client_id']);

    if (empty($code) || empty($state) || !hash_equals($sessState, $state) || $targetClientId <= 0) {
        $errDesc = (string)($_GET['error_description'] ?? 'Invalid or expired state verification.');
        flash('error', 'Meta OAuth connection failed: ' . $errDesc);
        redirect('index.php?page=accounts');
    }

    $res = MetaGraph::exchangeCodeForTokens($pdo, $code);
    if (!$res['success']) {
        flash('error', 'Meta Token Exchange Failed: ' . ($res['error'] ?? 'Unknown error'));
        redirect('index.php?page=accounts');
    }

    $pages = $res['pages'] ?? [];
    $expiresAt = $res['expires_at'] ?? date('Y-m-d H:i:s', time() + 5184000);
    $connectedCount = 0;

    foreach ($pages as $p) {
        $pageId = (string)($p['id'] ?? '');
        $pageName = (string)($p['name'] ?? 'Facebook Page');
        $pageToken = (string)($p['access_token'] ?? '');

        // 1. Link / Upsert Facebook Page Account
        $chkFb = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'facebook' AND meta_page_id = ?");
        $chkFb->execute([$targetClientId, $pageId]);
        $existingFbId = $chkFb->fetchColumn();

        if ($existingFbId) {
            $pdo->prepare("UPDATE accounts SET access_token = ?, token_expires_at = ?, label = ?, connection_status = 'connected' WHERE id = ?")
                ->execute([$pageToken, $expiresAt, $pageName . ' (Page)', $existingFbId]);
        } else {
            $pdo->prepare("INSERT INTO accounts(client_id,platform,label,external_id,connection_status,access_token,token_expires_at,meta_page_id,created_at) VALUES(?,?,?,?,'connected',?,?,?,?)")
                ->execute([$targetClientId, 'facebook', $pageName . ' (Page)', $pageId, $pageToken, $expiresAt, $pageId, now()]);
        }
        $connectedCount++;

        // 2. Link connected Instagram Business Account if available
        if (!empty($p['instagram_business_account']['id'])) {
            $igId = (string)$p['instagram_business_account']['id'];
            $igUsername = (string)($p['instagram_business_account']['username'] ?? $pageName . '_ig');

            $chkIg = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'instagram' AND meta_ig_id = ?");
            $chkIg->execute([$targetClientId, $igId]);
            $existingIgId = $chkIg->fetchColumn();

            if ($existingIgId) {
                $pdo->prepare("UPDATE accounts SET access_token = ?, token_expires_at = ?, label = ?, connection_status = 'connected', meta_page_id = ? WHERE id = ?")
                    ->execute([$pageToken, $expiresAt, '@' . $igUsername . ' (Instagram)', $pageId, $existingIgId]);
            } else {
                $pdo->prepare("INSERT INTO accounts(client_id,platform,label,external_id,connection_status,access_token,token_expires_at,meta_page_id,meta_ig_id,created_at) VALUES(?,?,?,?,'connected',?,?,?,?,?)")
                    ->execute([$targetClientId, 'instagram', '@' . $igUsername . ' (Instagram)', $igId, $pageToken, $expiresAt, $pageId, $igId, now()]);
            }
            $connectedCount++;
        }
    }

    $pdo->prepare("UPDATE jobs SET status = 'queued', error_message = NULL, scheduled_for = ? WHERE client_id = ? AND status = 'failed' AND error_message LIKE '%(#200)%'")
        ->execute([now(), $targetClientId]);
    $autoRetry = QueueEngine::process(25);
    $extraMsg = !empty($autoRetry['published']) ? " Also auto-published {$autoRetry['published']} pending post(s)!" : "";

    $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
        ->execute([$targetClientId, "Meta Real-Time Accounts connected: {$connectedCount} channel profile(s) synced", 'success', now()]);

    flash('success', "Meta integration successful! Synchronized {$connectedCount} Facebook Page & Instagram account(s).{$extraMsg}");
    redirect('index.php?page=campaigns');
}

// ----------------------------------------------------------------------
// LinkedIn OAuth 2.0 Integration Handlers (GET)
// ----------------------------------------------------------------------
if ($page === 'linkedin-connect') {
    $clientId = (int)($_GET['client_id'] ?? 0);
    if ($clientId <= 0) {
        flash('error', 'Please choose a client workspace to link the LinkedIn account to.');
        redirect('index.php?page=accounts');
    }

    if (!LinkedInClient::isConfigured($pdo)) {
        flash('error', 'LinkedIn Client ID and Secret must be configured in Settings first.');
        redirect('index.php?page=settings');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['linkedin_oauth_state'] = $state;
    $_SESSION['linkedin_oauth_client_id'] = $clientId;

    $authUrl = LinkedInClient::getAuthorizationUrl($pdo, $state);
    if ($authUrl === '') {
        flash('error', 'Failed to generate LinkedIn authorization URL.');
        redirect('index.php?page=accounts');
    }

    header('Location: ' . $authUrl);
    exit;
}

if ($page === 'linkedin-callback') {
    $code = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    $sessState = (string)($_SESSION['linkedin_oauth_state'] ?? '');
    $targetClientId = (int)($_SESSION['linkedin_oauth_client_id'] ?? 0);

    unset($_SESSION['linkedin_oauth_state'], $_SESSION['linkedin_oauth_client_id']);

    if (empty($code) || empty($state) || !hash_equals($sessState, $state) || $targetClientId <= 0) {
        $errDesc = (string)($_GET['error_description'] ?? 'Invalid or expired state verification.');
        flash('error', 'LinkedIn OAuth connection failed: ' . $errDesc);
        redirect('index.php?page=accounts');
    }

    $res = LinkedInClient::exchangeCodeForTokens($pdo, $code);
    if (!$res['success']) {
        flash('error', 'LinkedIn Token Exchange Failed: ' . ($res['error'] ?? 'Unknown error'));
        redirect('index.php?page=accounts');
    }

    $accessToken = (string)$res['access_token'];
    $refreshToken = (string)($res['refresh_token'] ?? '');
    $expiresAt = (string)$res['expires_at'];
    $urn = (string)($res['urn'] ?? '');
    $name = (string)($res['name'] ?? 'LinkedIn Member');
    $label = $name . ' (LinkedIn)';

    // Upsert LinkedIn Account
    $chk = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'linkedin' AND (linkedin_urn = ? OR label = ?)");
    $chk->execute([$targetClientId, $urn, $label]);
    $existingId = $chk->fetchColumn();

    if ($existingId) {
        $pdo->prepare("
            UPDATE accounts 
            SET access_token = ?, refresh_token = ?, token_expires_at = ?, linkedin_urn = ?, label = ?, connection_status = 'connected' 
            WHERE id = ?
        ")->execute([$accessToken, $refreshToken, $expiresAt, $urn, $label, $existingId]);
    } else {
        $pdo->prepare("
            INSERT INTO accounts(client_id, platform, label, external_id, connection_status, access_token, refresh_token, token_expires_at, linkedin_urn, created_at)
            VALUES(?, 'linkedin', ?, ?, 'connected', ?, ?, ?, ?, ?)
        ")->execute([$targetClientId, $label, $urn, $accessToken, $refreshToken, $expiresAt, $urn, now()]);
    }

    $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
        ->execute([$targetClientId, "LinkedIn Account connected: {$label}", 'success', now()]);

    flash('success', "LinkedIn integration successful! Connected account '{$label}'.");
    redirect('index.php?page=accounts');
}

// ----------------------------------------------------------------------
// Pinterest OAuth 2.0 Integration Handlers (GET)
// ----------------------------------------------------------------------
if ($page === 'pinterest-connect') {
    $clientId = (int)($_GET['client_id'] ?? 0);
    if ($clientId <= 0) {
        flash('error', 'Please choose a client workspace to link Pinterest accounts to.');
        redirect('index.php?page=accounts');
    }

    if (!PinterestClient::isConfigured($pdo)) {
        flash('error', 'Pinterest App ID and Secret must be configured in Settings first.');
        redirect('index.php?page=settings');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['pinterest_oauth_state'] = $state;
    $_SESSION['pinterest_oauth_client_id'] = $clientId;

    $authUrl = PinterestClient::getAuthorizationUrl($pdo, $state);
    if ($authUrl === '') {
        flash('error', 'Failed to generate Pinterest authorization URL.');
        redirect('index.php?page=accounts');
    }

    header('Location: ' . $authUrl);
    exit;
}

if ($page === 'pinterest-callback') {
    $code = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    $sessState = (string)($_SESSION['pinterest_oauth_state'] ?? '');
    $targetClientId = (int)($_SESSION['pinterest_oauth_client_id'] ?? 0);

    unset($_SESSION['pinterest_oauth_state'], $_SESSION['pinterest_oauth_client_id']);

    if (empty($code) || empty($state) || !hash_equals($sessState, $state) || $targetClientId <= 0) {
        $errDesc = (string)($_GET['error_description'] ?? 'Invalid or expired state verification.');
        flash('error', 'Pinterest OAuth connection failed: ' . $errDesc);
        redirect('index.php?page=accounts');
    }

    $res = PinterestClient::exchangeCodeForTokens($pdo, $code);
    if (!$res['success']) {
        flash('error', 'Pinterest Token Exchange Failed: ' . ($res['error'] ?? 'Unknown error'));
        redirect('index.php?page=accounts');
    }

    $accessToken = (string)$res['access_token'];
    $refreshToken = (string)($res['refresh_token'] ?? '');
    $expiresAt = (string)$res['expires_at'];

    // Fetch user boards
    $boards = PinterestClient::getBoards($accessToken);
    $connectedCount = 0;

    if (!empty($boards)) {
        foreach ($boards as $b) {
            $boardId = (string)($b['id'] ?? '');
            $boardName = (string)($b['name'] ?? 'Pinterest Board');
            $label = $boardName . ' (Board)';

            $chk = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'pinterest' AND (pinterest_board_id = ? OR external_id = ?)");
            $chk->execute([$targetClientId, $boardId, $boardId]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $pdo->prepare("
                    UPDATE accounts 
                    SET access_token = ?, refresh_token = ?, token_expires_at = ?, pinterest_board_id = ?, label = ?, connection_status = 'connected' 
                    WHERE id = ?
                ")->execute([$accessToken, $refreshToken, $expiresAt, $boardId, $label, $existingId]);
            } else {
                $pdo->prepare("
                    INSERT INTO accounts(client_id, platform, label, external_id, connection_status, access_token, refresh_token, token_expires_at, pinterest_board_id, page_category, created_at)
                    VALUES(?, 'pinterest', ?, ?, 'connected', ?, ?, ?, ?, 'Board', ?)
                ")->execute([$targetClientId, $label, $boardId, $accessToken, $refreshToken, $expiresAt, $boardId, now()]);
            }
            $connectedCount++;
        }
    } else {
        $label = 'Pinterest Primary Profile';
        $chk = $pdo->prepare("SELECT id FROM accounts WHERE client_id = ? AND platform = 'pinterest' LIMIT 1");
        $chk->execute([$targetClientId]);
        $existingId = $chk->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE accounts SET access_token = ?, refresh_token = ?, token_expires_at = ?, connection_status = 'connected' WHERE id = ?")
                ->execute([$accessToken, $refreshToken, $expiresAt, $existingId]);
        } else {
            $pdo->prepare("INSERT INTO accounts(client_id, platform, label, connection_status, access_token, refresh_token, token_expires_at, created_at) VALUES(?, 'pinterest', ?, 'connected', ?, ?, ?, ?)")
                ->execute([$targetClientId, $label, $accessToken, $refreshToken, $expiresAt, now()]);
        }
        $connectedCount = 1;
    }

    $pdo->prepare("INSERT INTO activity(client_id,message,status,created_at) VALUES(?,?,?,?)")
        ->execute([$targetClientId, "Pinterest integration connected: {$connectedCount} board/account(s) synced", 'success', now()]);

    flash('success', "Pinterest integration successful! Connected {$connectedCount} board profile(s).");
    redirect('index.php?page=accounts');
}

// ----------------------------------------------------------------------
// ----------------------------------------------------------------------
// Workspace Switcher Route
// ----------------------------------------------------------------------
if ($page === 'switch-workspace') {
    $cid = (int)($_REQUEST['client_id'] ?? 0);
    if ($cid > 0) {
        $chk = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
        $chk->execute([$cid]);
        $cName = $chk->fetchColumn();
        if ($cName) {
            $_SESSION['active_client_id'] = $cid;
            flash('success', "Active workspace switched to: {$cName}");
        }
    } else {
        $_SESSION['active_client_id'] = 0;
        flash('info', "Switched to All Workspaces view.");
    }
    $returnPage = preg_replace('/[^a-z0-9_-]/i', '', (string)($_REQUEST['return'] ?? 'dashboard'));
    if (!in_array($returnPage, ['dashboard', 'campaigns', 'accounts', 'comments', 'manual', 'reports', 'settings', 'clients'], true)) {
        $returnPage = 'dashboard';
    }
    redirect("index.php?page={$returnPage}");
}

// ----------------------------------------------------------------------
// GET Data Exports
// ----------------------------------------------------------------------
if ($page === 'export-csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wsa_publisher_report_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Job ID', 'Campaign Name', 'Client Name', 'Platform', 'Status', 'Execution Mode', 'Scheduled For', 'Created At']);
    $rows = $pdo->query("
        SELECT j.id, c.name as campaign_name, cl.name as client_name, j.platform, j.status, j.execution_mode, j.scheduled_for, j.created_at 
        FROM jobs j 
        JOIN campaigns c ON c.id=j.campaign_id 
        JOIN clients cl ON cl.id=j.client_id 
        ORDER BY j.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $pName = platforms()[$r['platform']][0] ?? $r['platform'];
        fputcsv($out, [
            $r['id'],
            $r['campaign_name'],
            $r['client_name'],
            $pName,
            $r['status'],
            $r['execution_mode'],
            $r['scheduled_for'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ----------------------------------------------------------------------
// View Rendering Dispatcher with Workspace Context
// ----------------------------------------------------------------------

function render(string $viewName, string $title, string $activePage, array $data = []): void {
    global $allClients, $activeClientId, $activeClientName;
    $data['allClients'] = $allClients;
    $data['activeClientId'] = $activeClientId;
    $data['activeClientName'] = $activeClientName;
    extract($data);
    require __DIR__ . '/views/layout_start.php';
    require __DIR__ . '/views/' . $viewName . '.php';
    require __DIR__ . '/views/layout_end.php';
    exit;
}

if ($page === 'dashboard') {
    $whereClient = $activeClientId > 0 ? "WHERE client_id = {$activeClientId}" : "";
    $whereClientJ = $activeClientId > 0 ? "WHERE j.client_id = {$activeClientId}" : "";
    $whereClientAct = $activeClientId > 0 ? "WHERE a.client_id = {$activeClientId}" : "";

    $stats = [
        'Clients'        => (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn(),
        'Accounts'       => (int)$pdo->query("SELECT COUNT(*) FROM accounts " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId}" : ""))->fetchColumn(),
        'Scheduled'      => (int)$pdo->query("SELECT COUNT(*) FROM campaigns " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId} AND status='scheduled'" : "WHERE status='scheduled'"))->fetchColumn(),
        'Published'      => (int)$pdo->query("SELECT COUNT(*) FROM jobs " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId} AND status='published'" : "WHERE status='published'"))->fetchColumn(),
        'Manual Pending' => (int)$pdo->query("SELECT COUNT(*) FROM jobs " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId} AND status='manual_pending'" : "WHERE status='manual_pending'"))->fetchColumn(),
        'Failed'         => (int)$pdo->query("SELECT COUNT(*) FROM jobs " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId} AND status='failed'" : "WHERE status='failed'"))->fetchColumn(),
    ];
    $jobs = $pdo->query("
        SELECT j.*, c.name campaign_name, cl.name client_name, a.label account_label 
        FROM jobs j 
        JOIN campaigns c ON c.id=j.campaign_id 
        JOIN clients cl ON cl.id=j.client_id 
        LEFT JOIN accounts a ON a.id=j.account_id
        {$whereClientJ}
        ORDER BY j.id DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    $accts = [];
    $acctQuery = $activeClientId > 0 
        ? "SELECT platform, COUNT(*) c FROM accounts WHERE client_id = {$activeClientId} GROUP BY platform"
        : "SELECT platform, COUNT(*) c FROM accounts GROUP BY platform";
    foreach ($pdo->query($acctQuery)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $accts[$r['platform']] = $r['c'];
    }
    $acts = $pdo->query("
        SELECT a.*, cl.name client_name 
        FROM activity a 
        LEFT JOIN clients cl ON cl.id=a.client_id 
        {$whereClientAct}
        ORDER BY a.id DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    render('dashboard', 'Dashboard', 'dashboard', compact('stats', 'jobs', 'accts', 'acts'));
}

if ($page === 'clients') {
    $clients = $pdo->query("SELECT * FROM clients ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    render('clients', 'Clients', 'clients', compact('clients'));
}

if ($page === 'accounts') {
    $clients = $allClients;
    $whereAcc = $activeClientId > 0 ? "WHERE a.client_id = {$activeClientId}" : "";
    $accounts = $pdo->query("
        SELECT a.*, c.name client_name 
        FROM accounts a 
        JOIN clients c ON c.id=a.client_id 
        {$whereAcc}
        ORDER BY a.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $fbGroupCounts = $pdo->query("
        SELECT account_id, COUNT(*) as cnt 
        FROM group_targets 
        WHERE account_id IS NOT NULL AND account_id > 0 
        GROUP BY account_id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    render('accounts', 'Connect Accounts', 'accounts', compact('clients', 'accounts', 'fbGroupCounts'));
}

if ($page === 'campaigns') {
    $clients = $allClients;
    $whereCmp = $activeClientId > 0 ? "WHERE c.client_id = {$activeClientId}" : "";
    $campaigns = $pdo->query("
        SELECT c.*, cl.name client_name,
               (SELECT error_message FROM jobs WHERE campaign_id = c.id AND status = 'failed' LIMIT 1) AS last_job_error
        FROM campaigns c 
        JOIN clients cl ON cl.id=c.client_id 
        {$whereCmp}
        ORDER BY c.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch accounts available for campaign targeting
    $accWhere = $activeClientId > 0 ? "WHERE a.client_id = {$activeClientId}" : "";
    $availableAccounts = $pdo->query("
        SELECT a.*, c.name client_name 
        FROM accounts a 
        JOIN clients c ON c.id=a.client_id 
        {$accWhere}
        ORDER BY a.platform, a.label
    ")->fetchAll(PDO::FETCH_ASSOC);

    render('campaigns', 'Campaigns', 'campaigns', compact('clients', 'campaigns', 'availableAccounts'));
}

if ($page === 'manual') {
    $whereMan = $activeClientId > 0 ? "AND j.client_id = {$activeClientId}" : "";
    $jobs = $pdo->query("
        SELECT j.*, c.name campaign_name, c.message, c.link_url, c.media_path, cl.name client_name, a.label account_label 
        FROM jobs j 
        JOIN campaigns c ON c.id=j.campaign_id 
        JOIN clients cl ON cl.id=j.client_id 
        LEFT JOIN accounts a ON a.id=j.account_id
        WHERE j.status='manual_pending' {$whereMan}
        ORDER BY j.scheduled_for
    ")->fetchAll(PDO::FETCH_ASSOC);
    $queuedJobs = $pdo->query("
        SELECT j.*, c.name campaign_name, cl.name client_name, a.label account_label 
        FROM jobs j 
        JOIN campaigns c ON c.id=j.campaign_id 
        JOIN clients cl ON cl.id=j.client_id 
        LEFT JOIN accounts a ON a.id=j.account_id
        WHERE j.status='queued' {$whereMan}
        ORDER BY j.scheduled_for LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    render('manual', 'Manual Queue', 'manual', compact('jobs', 'queuedJobs'));
}

if ($page === 'reports') {
    $whereRep = $activeClientId > 0 ? "WHERE j.client_id = {$activeClientId}" : "";
    $rows = $pdo->query("
        SELECT cl.name client_name, j.platform, j.status, COUNT(*) total 
        FROM jobs j 
        JOIN clients cl ON cl.id=j.client_id 
        {$whereRep}
        GROUP BY cl.name, j.platform, j.status 
        ORDER BY cl.name, total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    render('reports', 'Reports', 'reports', compact('rows'));
}

if ($page === 'comments') {
    $whereClientAcc = $activeClientId > 0 ? "AND a.client_id = {$activeClientId}" : "";
    $whereClientCom = $activeClientId > 0 ? "WHERE cm.client_id = {$activeClientId}" : "";

    $connectedAccounts = $pdo->query("
        SELECT a.*, c.name client_name 
        FROM accounts a 
        JOIN clients c ON c.id=a.client_id 
        WHERE a.platform IN ('facebook', 'instagram', 'linkedin', 'pinterest') AND a.access_token IS NOT NULL AND a.access_token != ''
        {$whereClientAcc}
        ORDER BY a.platform, a.label
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Default Main Facebook Page (Default: WSA Digital Agency ID 24)
    $defaultMainPageId = (int)(get_system_setting('default_main_page_id', '24') ?: 24);
    $mainPageSt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
    $mainPageSt->execute([$defaultMainPageId]);
    $defaultMainPage = $mainPageSt->fetch(PDO::FETCH_ASSOC);
    if (!$defaultMainPage) {
        $mainPageSt = $pdo->query("SELECT * FROM accounts WHERE platform = 'facebook' ORDER BY id ASC LIMIT 1");
        $defaultMainPage = $mainPageSt->fetch(PDO::FETCH_ASSOC);
        if ($defaultMainPage) {
            $defaultMainPageId = (int)$defaultMainPage['id'];
        }
    }

    // Fetch Last 7 Days Live Posts & Reels from the Default Main Page
    $sevenDayPosts = [];
    $rawPosts = [];
    if ($defaultMainPage && !empty($defaultMainPage['access_token']) && !empty($defaultMainPage['external_id'])) {
        $rawPosts = MetaGraph::fetchLatestPagePosts((string)$defaultMainPage['external_id'], (string)$defaultMainPage['access_token'], 25);
        $sevenDaysAgo = strtotime('-7 days');
        foreach ($rawPosts as $p) {
            $createdTs = strtotime($p['created_time'] ?? '');
            if ($createdTs >= $sevenDaysAgo) {
                $sevenDayPosts[] = $p;
            }
        }
        if (count($sevenDayPosts) < 3 && !empty($rawPosts)) {
            $sevenDayPosts = array_slice($rawPosts, 0, 6);
        }
    }
    $livePagePosts = $rawPosts;

    // Filter Booster Pages (connected Facebook pages excluding the default main page)
    $boosterPages = array_values(array_filter($connectedAccounts, fn($a) => $a['platform'] === 'facebook' && (int)$a['id'] !== (int)$defaultMainPageId));

    $recentComments = $pdo->query("
        SELECT cm.*, c.name client_name, a.label account_label 
        FROM comments cm 
        JOIN clients c ON c.id=cm.client_id 
        LEFT JOIN accounts a ON a.id=cm.account_id 
        {$whereClientCom}
        ORDER BY cm.id DESC LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentWsaPosts = $pdo->query("
        SELECT j.id, j.response_id, j.platform, c.name campaign_name, c.message, j.created_at, a.label account_label
        FROM jobs j
        JOIN campaigns c ON c.id=j.campaign_id
        LEFT JOIN accounts a ON a.id=j.account_id
        WHERE j.status='published' AND j.response_id IS NOT NULL AND j.response_id != ''
        ORDER BY j.id DESC LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC);

    render('comments', 'Likes & Comments Booster', 'comments', compact(
        'connectedAccounts', 'recentComments', 'recentWsaPosts',
        'defaultMainPage', 'defaultMainPageId', 'sevenDayPosts', 'livePagePosts', 'boosterPages'
    ));
}

if ($page === 'groups') {
    $whereClient = $activeClientId > 0 ? "WHERE client_id = {$activeClientId}" : "";
    $whereClientAcc = $activeClientId > 0 ? "AND client_id = {$activeClientId}" : "";

    $senderProfiles = $pdo->query("SELECT * FROM sender_profiles {$whereClient} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $connectedFbAccounts = $pdo->query("SELECT * FROM accounts WHERE platform = 'facebook' {$whereClientAcc} ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Compute account group counts across all groups for client
    $rawCounts = $pdo->query("
        SELECT account_id, COUNT(*) as cnt 
        FROM group_targets 
        " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId}" : "") . " 
        GROUP BY account_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $accountGroupCounts = [];
    foreach ($rawCounts as $rc) {
        $accountGroupCounts[(int)$rc['account_id']] = (int)$rc['cnt'];
    }
    $totalGroupsCount = (int)$pdo->query("SELECT COUNT(*) FROM group_targets " . ($activeClientId > 0 ? "WHERE client_id = {$activeClientId}" : ""))->fetchColumn();

    $filterAccountId = (isset($_GET['account_id']) && $_GET['account_id'] !== '') ? (int)$_GET['account_id'] : null;

    $whereGroupFilter = [];
    if ($activeClientId > 0) {
        $whereGroupFilter[] = "gt.client_id = {$activeClientId}";
    }
    if ($filterAccountId !== null) {
        if ($filterAccountId === 0) {
            $whereGroupFilter[] = "(gt.account_id IS NULL OR gt.account_id = 0)";
        } else {
            $whereGroupFilter[] = "gt.account_id = {$filterAccountId}";
        }
    }
    $whereGroupSql = !empty($whereGroupFilter) ? "WHERE " . implode(" AND ", $whereGroupFilter) : "";

    $targetGroups = $pdo->query("
        SELECT gt.*, a.label AS account_label, a.external_id AS account_ext_id, a.platform AS account_platform, a.page_category AS account_category, sp.name AS sender_name
        FROM group_targets gt
        LEFT JOIN accounts a ON a.id = gt.account_id
        LEFT JOIN sender_profiles sp ON sp.id = gt.sender_profile_id
        {$whereGroupSql}
        ORDER BY CASE WHEN a.label IS NULL THEN 1 ELSE 0 END, a.label ASC, gt.niche_category ASC, gt.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $whereQueue = $activeClientId > 0 ? "WHERE q.client_id = {$activeClientId}" : "";
    $shareQueue = $pdo->query("
        SELECT q.*, 
               sp.name sender_name, 
               sa.label sender_account_label,
               sa.external_id sender_page_id,
               gt.name group_name, gt.group_url, gt.niche_category, c.name campaign_name
        FROM group_share_queue q
        LEFT JOIN sender_profiles sp ON sp.id = q.sender_profile_id
        LEFT JOIN accounts sa ON sa.id = q.sender_account_id
        JOIN group_targets gt ON gt.id = q.group_id
        LEFT JOIN campaigns c ON c.id = q.campaign_id
        {$whereQueue}
        ORDER BY q.status ASC, q.scheduled_for ASC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);

    $publishedCampaigns = $pdo->query("
        SELECT DISTINCT c.id, c.name, j.response_id, c.link_url
        FROM campaigns c
        JOIN jobs j ON j.campaign_id = c.id
        WHERE j.platform = 'facebook' AND j.status = 'published' AND j.response_id IS NOT NULL AND j.response_id != ''
        " . ($activeClientId > 0 ? "AND c.client_id = {$activeClientId}" : "") . "
        ORDER BY c.id DESC LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);

    $niches = array_unique(array_filter(array_column($targetGroups, 'niche_category')));
    if (empty($niches)) {
        $niches = ['Digital Marketing', 'Business & Startups', 'E-Commerce', 'Home & Decor', 'Social Media'];
    }

    $activeSendersCount = count(array_filter($senderProfiles, fn($s) => (bool)$s['is_enabled']));
    $todayDateStr = date('Y-m-d');
    $queueSharesToday = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM group_share_queue 
        WHERE status = 'shared' AND DATE(shared_at) = '{$todayDateStr}'
        " . ($activeClientId > 0 ? "AND client_id = {$activeClientId}" : "")
    )->fetchColumn();
    $senderProfileShares = (int)array_sum(array_column($senderProfiles, 'shares_today'));
    $totalSharesToday = $queueSharesToday + $senderProfileShares;

    $stats = [
        'total_shares_today' => $totalSharesToday,
        'target_goal' => 100,
        'active_senders_count' => $activeSendersCount,
        'total_senders_count' => count($senderProfiles),
        'total_groups_count' => $totalGroupsCount,
    ];

    // Default Main Page (Default: WSA Digital Agency)
    $defaultMainPageId = (int)(get_system_setting('default_main_page_id', '24') ?: 24);
    $mainPageSt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
    $mainPageSt->execute([$defaultMainPageId]);
    $defaultMainPage = $mainPageSt->fetch(PDO::FETCH_ASSOC);
    if (!$defaultMainPage) {
        $mainPageSt = $pdo->query("SELECT * FROM accounts WHERE platform = 'facebook' ORDER BY id ASC LIMIT 1");
        $defaultMainPage = $mainPageSt->fetch(PDO::FETCH_ASSOC);
        if ($defaultMainPage) {
            $defaultMainPageId = (int)$defaultMainPage['id'];
        }
    }

    // Fetch 7-Day Posts & Reels from the Default Main Page
    $sevenDayPosts = [];
    $cachedLatestPost = null;
    $cachedPostRaw = get_system_setting('group_autopilot_cached_post', '');
    if (!empty($cachedPostRaw)) {
        $cachedLatestPost = json_decode($cachedPostRaw, true);
    }

    if ($defaultMainPage && !empty($defaultMainPage['access_token']) && !empty($defaultMainPage['external_id'])) {
        $rawPosts = MetaGraph::fetchLatestPagePosts((string)$defaultMainPage['external_id'], (string)$defaultMainPage['access_token'], 25);
        $sevenDaysAgo = strtotime('-7 days');
        foreach ($rawPosts as $p) {
            $createdTs = strtotime($p['created_time'] ?? '');
            if ($createdTs >= $sevenDaysAgo) {
                $sevenDayPosts[] = $p;
            }
        }
        // If fewer than 3 in exact 7-day range, keep recent posts up to 6 so user always has posts to engage with
        if (count($sevenDayPosts) < 3 && !empty($rawPosts)) {
            $sevenDayPosts = array_slice($rawPosts, 0, 6);
        }
        if (!empty($rawPosts[0])) {
            $cachedLatestPost = $rawPosts[0];
            set_system_setting('group_autopilot_cached_post', json_encode($cachedLatestPost));
        }
    }

    // Auto-Pilot Settings
    $autoPilotEnabled = (get_system_setting('group_autopilot_enabled', '1') ?? '1') === '1';
    $autoPilotCount = (int)(get_system_setting('group_autopilot_daily_count', '5') ?: 5);
    $autoPilotInterval = (int)(get_system_setting('group_autopilot_interval_mins', '30') ?: 30);
    $autoPilotLastRun = get_system_setting('group_autopilot_last_run_date', '');
    $autoPilotAccountId = $defaultMainPageId;
    $autoPilotLastPostUrl = get_system_setting('group_autopilot_last_post_url', '');

    $livePagePosts = $rawPosts ?? [];

    render('groups', 'Group Auto-Distribution & Anti-Ban Drip', 'groups', compact(
        'senderProfiles', 'targetGroups', 'shareQueue', 'publishedCampaigns', 'niches', 
        'stats', 'connectedFbAccounts', 'accountGroupCounts', 'totalGroupsCount', 'filterAccountId',
        'autoPilotEnabled', 'autoPilotCount', 'autoPilotInterval', 'autoPilotLastRun', 
        'autoPilotAccountId', 'autoPilotLastPostUrl', 'cachedLatestPost',
        'defaultMainPage', 'sevenDayPosts', 'defaultMainPageId', 'livePagePosts'
    ));
}

if ($page === 'settings') {
    render('settings', 'Settings', 'settings');
}

// 404 Fallback
http_response_code(404);
render('not_found', 'Not Found', 'not_found');
