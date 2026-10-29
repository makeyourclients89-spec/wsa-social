<?php
declare(strict_types=1);

/**
 * WSA Social - Automated Deployment Webhook for Shared Hosting
 * 
 * Works with GitHub Webhooks (push event) or direct browser trigger.
 * 
 * How to use:
 * 1. Set your secret token below or in query parameter: ?secret=YOUR_TOKEN
 * 2. In GitHub Repo -> Settings -> Webhooks:
 *    Payload URL: https://yourdomain.com/deploy.php?secret=YOUR_TOKEN
 *    Content type: application/json
 *    Events: Just the push event
 */

header('Content-Type: application/json; charset=utf-8');

// CHANGE THIS SECRET KEY FOR YOUR SERVER SECURITY:
define('DEFAULT_DEPLOY_SECRET', 'wsa_deploy_secret_2026');

// Retrieve provided secret from query param or header
$providedSecret = (string)($_GET['secret'] ?? $_SERVER['HTTP_X_DEPLOY_SECRET'] ?? '');
$configuredSecret = DEFAULT_DEPLOY_SECRET;

// Also check system_settings if database is available
try {
    $dbPath = __DIR__ . '/storage/wsa_publisher.sqlite';
    if (file_exists($dbPath)) {
        $pdo = new PDO('sqlite:' . $dbPath);
        $st = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'deploy_secret'");
        $st->execute();
        $dbSecret = $st->fetchColumn();
        if (!empty($dbSecret)) {
            $configuredSecret = (string)$dbSecret;
        }
    }
} catch (\Throwable $e) {}

// Authenticate request
$isAuthorized = false;
if (!empty($providedSecret) && hash_equals($configuredSecret, $providedSecret)) {
    $isAuthorized = true;
}

// GitHub Webhook HMAC Signature verification (if secret configured)
$hubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (!$isAuthorized && !empty($hubSignature)) {
    $payload = file_get_contents('php://input');
    $expectedHash = 'sha256=' . hash_hmac('sha256', (string)$payload, $configuredSecret);
    if (hash_equals($expectedHash, $hubSignature)) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Invalid deploy secret key.',
        'hint' => 'Pass valid ?secret=YOUR_TOKEN in URL or setup in GitHub Webhook secret.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$startTime = microtime(true);
$logs = [];
$methodUsed = '';

// METHOD 1: Check if Git is available via shell_exec / exec
$execAvailable = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))));
$shellAvailable = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))));

$gitDir = __DIR__ . '/.git';

if (($execAvailable || $shellAvailable) && is_dir($gitDir)) {
    $methodUsed = 'git_pull';
    $commands = [
        'git config --global --add safe.directory ' . escapeshellarg(__DIR__),
        'git fetch origin main',
        'git reset --hard origin/main',
        'git pull origin main'
    ];

    foreach ($commands as $cmd) {
        $out = [];
        $code = 0;
        if ($execAvailable) {
            exec("cd " . escapeshellarg(__DIR__) . " && {$cmd} 2>&1", $out, $code);
            $logs[] = "\$ {$cmd} (exit {$code}):\n" . implode("\n", $out);
        } else {
            $outStr = (string)shell_exec("cd " . escapeshellarg(__DIR__) . " && {$cmd} 2>&1");
            $logs[] = "\$ {$cmd}:\n" . $outStr;
        }
    }
} else {
    // METHOD 2: Direct zip sync via GitHub repository download
    // Safe extraction preserving storage/ and uploads/
    $methodUsed = 'zip_sync';
    $repoUrl = 'https://github.com/makeyourclients89-spec/wsa-social/archive/refs/heads/main.zip';
    
    $tempZip = sys_get_temp_dir() . '/wsa_update_' . time() . '.zip';
    
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 45,
            'user_agent' => 'WSA-Social-AutoDeploy'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $zipData = @file_get_contents($repoUrl, false, $ctx);
    if ($zipData === false) {
        $logs[] = "Failed to download update zip directly from {$repoUrl}.";
    } else {
        file_put_contents($tempZip, $zipData);
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tempZip) === true) {
                $extractCount = 0;
                $skippedCount = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    // Remove top-level zip folder (e.g. wsa-social-main/)
                    $subPath = preg_replace('~^[^/]+/~', '', $entryName);
                    if ($subPath === '' || str_ends_with($subPath, '/')) continue;

                    // NEVER overwrite persistent user data
                    if (str_starts_with($subPath, 'storage/') || str_starts_with($subPath, 'uploads/')) {
                        $skippedCount++;
                        continue;
                    }

                    $targetFile = __DIR__ . '/' . $subPath;
                    $dir = dirname($targetFile);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }

                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($targetFile, $content);
                        $extractCount++;
                    }
                }
                $zip->close();
                $logs[] = "Extracted {$extractCount} updated files successfully. (Preserved {$skippedCount} database/upload files).";
            } else {
                $logs[] = "ZipArchive failed to open downloaded zip file.";
            }
        } else {
            $logs[] = "PHP ZipArchive extension is not enabled on this shared hosting server.";
        }
        @unlink($tempZip);
    }
}

$duration = round(microtime(true) - $startTime, 2);

// Write to deploy log
$logLine = "[" . date('Y-m-d H:i:s') . "] Deploy via {$methodUsed} in {$duration}s\n" . implode("\n", $logs) . "\n-------------------------\n";
@file_put_contents(__DIR__ . '/storage/deploy_log.txt', $logLine, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => "WSA Social successfully updated on server in {$duration}s!",
    'method' => $methodUsed,
    'timestamp' => date('Y-m-d H:i:s'),
    'duration_seconds' => $duration,
    'logs' => $logs
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
