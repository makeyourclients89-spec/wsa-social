<?php
declare(strict_types=1);

/**
 * WSA Multi-Channel Publisher - Autonomous Background Worker (CRON)
 *
 * Can be triggered via:
 * 1. CLI:
 *    C:\xampp\php\php.exe C:\xampp\htdocs\wsa-social\cron.php
 * 2. HTTP GET / POST:
 *    http://localhost/wsa-social/cron.php?secret=wsa_cron_2026
 *    (or from localhost / loopback)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/queue.php';
require_once __DIR__ . '/includes/meta.php';
require_once __DIR__ . '/includes/linkedin.php';
require_once __DIR__ . '/includes/pinterest.php';

$isCli = (php_sapi_name() === 'cli');

// Basic security check for web requests
if (!$isCli) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost'], true);
    $secret = (string)($_GET['secret'] ?? $_POST['secret'] ?? '');
    $cronSecret = get_system_setting('cron_secret') ?: 'wsa_cron_2026';

    if (!$isLocal && $secret !== $cronSecret) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized cron request.']);
        exit;
    }
}

$now = now();
set_system_setting('runner_last_heartbeat', $now);

// 1. Process Due Automated Publishing Jobs
$result = QueueEngine::process(50);

// 2. Process Due Facebook Group Shares
$grpShareResult = QueueEngine::processGroupShareQueue(10);

// 3. Autonomous Daily Group Auto-Pilot (e.g. WSA Digital Agency latest post/reel to 5 groups)
$apResult = null;
$autoPilotEnabled = (get_system_setting('group_autopilot_enabled', '1') ?? '1') === '1';
if ($autoPilotEnabled) {
    $lastRunDate = get_system_setting('group_autopilot_last_run_date', '');
    $todayDate = date('Y-m-d');
    if ($lastRunDate !== $todayDate) {
        $sharesCount = (int)(get_system_setting('group_autopilot_daily_count', '5') ?: 5);
        $intervalMins = (int)(get_system_setting('group_autopilot_interval_mins', '30') ?: 30);
        $targetAccId = (int)(get_system_setting('group_autopilot_account_id', '24') ?: 24);

        $apResult = QueueEngine::generateGroupAutoPilotQueue(2, $targetAccId, $sharesCount, $intervalMins);
        if ($apResult['success']) {
            set_system_setting('group_autopilot_last_run_date', $todayDate);
            set_system_setting('group_autopilot_last_post_url', $apResult['post_url'] ?? '');
        }
    }
}

if ($isCli) {
    echo "========================================================\n";
    echo "WSA CRON Worker Finished at {$now}\n";
    echo "Published Jobs: {$result['published']}, Failed/Deferred: {$result['failed']}\n";
    echo "Group Shares Processed: {$grpShareResult['processed']}, Completed: {$grpShareResult['shared']}\n";
    if ($apResult) {
        echo "Group Auto-Pilot: " . ($apResult['success'] ? "Queued {$apResult['count']} groups for {$apResult['account']}" : "Skipped/Error: " . ($apResult['error'] ?? 'Unknown')) . "\n";
    }
    echo "========================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => $now,
        'published' => $result['published'],
        'failed' => $result['failed'],
        'group_shares' => $grpShareResult,
        'autopilot' => $apResult,
        'errors' => $result['errors'] ?? []
    ], JSON_PRETTY_PRINT);
}
