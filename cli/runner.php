<?php
declare(strict_types=1);

/**
 * WSA Multi-Channel Publisher - Background Queue Runner
 *
 * Usage:
 *   Windows (Task Scheduler / PowerShell / CMD):
 *     C:\xampp\php\php.exe C:\xampp\htdocs\wsa-social\cli\runner.php
 *
 *   Linux / Cron:
 *     * * * * * /usr/bin/php /var/www/wsa-social/cli/runner.php >> /var/log/wsa_runner.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This worker can only be invoked from the command line interface.\n");
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/queue.php';
require_once dirname(__DIR__) . '/includes/meta.php';
require_once dirname(__DIR__) . '/includes/linkedin.php';
require_once dirname(__DIR__) . '/includes/pinterest.php';

$now = now();
echo "========================================================\n";
echo "WSA Multi-Channel Publishing Runner: Started at {$now}\n";
echo "========================================================\n";

$logger = function(string $msg, string $type) {
    $prefix = match($type) {
        'info'    => '[INFO] -> ',
        'success' => ' [PUBLISHED]',
        'warn'    => ' [WARN] ',
        'error'   => ' [ERROR] ',
        default   => ' '
    };
    if (in_array($type, ['success', 'warn', 'error'], true)) {
        echo "{$prefix}{$msg}\n";
    } else {
        echo "{$prefix}{$msg}...";
    }
};

$result = QueueEngine::process(50, $logger);

echo "========================================================\n";
echo "[SUMMARY] Execution finished. Published: {$result['published']}, Failed/Deferred: {$result['failed']}\n";
echo "========================================================\n";
