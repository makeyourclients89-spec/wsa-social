<?php
declare(strict_types=1);

// ----------------------------------------------------------------------
// WSA Multi-Channel Publisher - Configuration & Security Initialization
// ----------------------------------------------------------------------

// Set Indian Standard Time (IST) as the application-wide default timezone
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

if (!defined('APP_SECURE')) {
    define('APP_SECURE', true);
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

ini_set('session.gc_maxlifetime', '2592000'); // 30 Days (2,592,000 seconds)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
session_set_cookie_params([
    'lifetime' => 2592000, // 30 Days Persistent Session (Never automatically logs out)
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['last_activity'] = time();

const APP_NAME = 'WSA Multi-Channel Publisher';
const ROOT_DIR = __DIR__ . '/..';
const DB_FILE = ROOT_DIR . '/storage/wsa_publisher.sqlite';
const UPLOAD_DIR = ROOT_DIR . '/uploads';
