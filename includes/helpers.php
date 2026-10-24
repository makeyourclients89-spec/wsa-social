<?php
declare(strict_types=1);

// ----------------------------------------------------------------------
// WSA Multi-Channel Publisher - Helper Functions & Utilities
// ----------------------------------------------------------------------

function client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

function check_login_rate_limit(string $ip): bool {
    $cutoff = time() - 900; // 15 minutes window
    $st = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?");
    $st->execute([$ip, $cutoff]);
    return ((int)$st->fetchColumn()) < 5;
}

function record_login_attempt(string $ip): void {
    $st = db()->prepare("INSERT INTO login_attempts(ip_address, attempted_at) VALUES(?, ?)");
    $st->execute([$ip, time()]);
}

function clear_login_attempts(string $ip): void {
    $st = db()->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $st->execute([$ip]);
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function now(): string {
    return date('Y-m-d H:i:s');
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}

function require_csrf(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid or expired form security token.');
    }
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function flash(string $key, ?string $value = null) {
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return;
    }
    $v = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $v;
}

function platforms(): array {
    return [
        'facebook'   => ['Facebook', 'social'],
        'instagram'  => ['Instagram', 'social'],
        'threads'    => ['Threads', 'social'],
        'linkedin'   => ['LinkedIn', 'social'],
        'x'          => ['X', 'social'],
        'tiktok'     => ['TikTok', 'social'],
        'youtube'    => ['YouTube', 'social'],
        'pinterest'  => ['Pinterest', 'social'],
        'telegram'   => ['Telegram', 'social'],
        'bluesky'    => ['Bluesky', 'social'],
        'wordpress'  => ['WordPress', 'blog'],
        'blogger'    => ['Blogger', 'blog'],
        'medium'     => ['Medium', 'blog'],
        'tumblr'     => ['Tumblr', 'blog'],
        'ghost'      => ['Ghost', 'blog'],
        'custom_cms' => ['Custom CMS', 'blog'],
        'flickr'     => ['Flickr', 'image'],
        'behance'    => ['Behance', 'image'],
        'imgur'      => ['Imgur', 'image'],
        'deviantart' => ['DeviantArt', 'image']
    ];
}

function get_system_setting(string $key, ?string $default = null): ?string {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (\Throwable $t) {
        return $default;
    }
}

function set_system_setting(string $key, string $value): bool {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO system_settings(key, value, updated_at) VALUES(?, ?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at");
        return $stmt->execute([$key, $value, date('Y-m-d H:i:s')]);
    } catch (\Throwable $t) {
        return false;
    }
}

function app_base_url(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/wsa-social/index.php';
    return $scheme . '://' . $host . $script;
}

function extract_social_post_id(string $input): string {
    $input = trim($input);
    if (empty($input)) {
        return '';
    }

    if (preg_match('/^[a-zA-Z0-9_:-]+$/', $input) && !str_starts_with($input, 'http')) {
        return $input;
    }

    if (preg_match('/[?&]story_fbid=([0-9]+)/', $input, $m)) {
        if (preg_match('/[?&]id=([0-9]+)/', $input, $p)) {
            return $p[1] . '_' . $m[1];
        }
        return $m[1];
    }

    if (preg_match('/facebook\.com\/(?:reel|reels)\/([0-9]+)/', $input, $m)) {
        return $m[1];
    }

    if (preg_match('/facebook\.com\/[^/]+\/(?:posts|videos)\/([0-9]+)/', $input, $m)) {
        return $m[1];
    }

    if (preg_match('/[?&]fbid=([0-9]+)/', $input, $m)) {
        return $m[1];
    }

    return $input;
}

function configure_curl_ssl(\CurlHandle $ch): void {
    $caBundle = defined('ROOT_DIR') ? ROOT_DIR . '/storage/cacert.pem' : dirname(__DIR__) . '/storage/cacert.pem';
    if (file_exists($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } elseif (PHP_OS_FAMILY === 'Windows') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
}
