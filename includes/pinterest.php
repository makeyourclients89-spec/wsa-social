<?php
/**
 * Pinterest API v5 Integration
 * Official OAuth 2.0, Multi-Board Synchronization, Pin Publishing & Auto-Commenting
 * Native PHP cURL implementation (Zero external Composer/SDK dependencies)
 */

declare(strict_types=1);

if (!defined('APP_SECURE')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

class PinterestClient
{
    private const OAUTH_DIALOG_URL = 'https://www.pinterest.com/oauth/';
    private const TOKEN_URL = 'https://api.pinterest.com/v5/oauth/token';
    private const REST_API_BASE = 'https://api.pinterest.com/v5';

    /**
     * Pinterest Scopes:
     * - boards:read, boards:write
     * - pins:read, pins:write
     * - user_accounts:read
     */
    public const REQUIRED_SCOPES = [
        'boards:read',
        'boards:write',
        'pins:read',
        'pins:write',
        'user_accounts:read',
    ];

    /**
     * Retrieve Pinterest settings from database
     */
    public static function getCredentials(PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT key, value FROM system_settings WHERE key IN ('pinterest_app_id', 'pinterest_app_secret', 'pinterest_redirect_uri')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'app_id'     => trim($rows['pinterest_app_id'] ?? ''),
            'app_secret' => trim($rows['pinterest_app_secret'] ?? ''),
            'redirect_uri' => trim($rows['pinterest_redirect_uri'] ?? ''),
        ];
    }

    /**
     * Check if Pinterest App is configured
     */
    public static function isConfigured(PDO $pdo): bool
    {
        $creds = self::getCredentials($pdo);
        return !empty($creds['app_id']) && !empty($creds['app_secret']);
    }

    /**
     * Resolve effective Redirect URI (auto-switches if DB has localhost but running on live domain)
     */
    public static function resolveRedirectUri(array $creds): string
    {
        $detected = self::detectRedirectUri();
        $configured = trim($creds['redirect_uri'] ?? '');
        if ($configured === '') {
            return $detected;
        }
        $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $configuredHost = strtolower((string)(parse_url($configured, PHP_URL_HOST) ?? ''));
        if ($configuredHost !== '' && $configuredHost !== $currentHost) {
            return $detected;
        }
        return $configured;
    }

    /**
     * Detect OAuth callback URL
     */
    public static function detectRedirectUri(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/wsa-social/index.php';

        return $scheme . '://' . $host . $script . '?page=pinterest-callback';
    }

    /**
     * Build Pinterest OAuth 2.0 Authorization URL
     */
    public static function getAuthorizationUrl(PDO $pdo, string $state): string
    {
        $creds = self::getCredentials($pdo);
        if (empty($creds['app_id'])) {
            return '';
        }

        $redirectUri = self::resolveRedirectUri($creds);

        $params = [
            'client_id'     => $creds['app_id'],
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(',', self::REQUIRED_SCOPES),
            'state'         => $state,
        ];

        return self::OAUTH_DIALOG_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange Authorization Code for Access Token and Refresh Token
     */
    public static function exchangeCodeForTokens(PDO $pdo, string $code): array
    {
        $creds = self::getCredentials($pdo);
        $redirectUri = self::resolveRedirectUri($creds);

        $postData = [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirectUri,
        ];

        $authHeader = 'Basic ' . base64_encode($creds['app_id'] . ':' . $creds['app_secret']);
        $headers = [
            'Authorization: ' . $authHeader,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $res = self::curlRequest(self::TOKEN_URL, 'POST', $postData, false, $headers);

        if (isset($res['error']) || isset($res['code'])) {
            $msg = $res['message'] ?? ($res['error_description'] ?? ($res['error'] ?? 'Pinterest token exchange failed.'));
            return ['success' => false, 'error' => $msg];
        }

        $accessToken = (string)($res['access_token'] ?? '');
        $refreshToken = (string)($res['refresh_token'] ?? '');
        $expiresIn = (int)($res['expires_in'] ?? 2592000); // 30 days
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'No access token returned from Pinterest.'];
        }

        return [
            'success'       => true,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $expiresAt,
        ];
    }

    /**
     * Fetch user's Pinterest Boards
     */
    public static function getBoards(string $accessToken): array
    {
        $url = self::REST_API_BASE . '/boards?page_size=50';
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ];

        $res = self::curlRequest($url, 'GET', [], true, $headers);

        if (isset($res['error']) || !isset($res['items'])) {
            return [];
        }

        return (array)$res['items'];
    }

    /**
     * Publish a Pin to a specified Board
     */
    public static function publishPin(string $boardId, string $accessToken, string $title, string $description, ?string $linkUrl = null, ?string $mediaPath = null): array
    {
        if (str_starts_with($accessToken, 'mock_')) {
            return ['success' => true, 'pin_id' => 'pin_mock_' . bin2hex(random_bytes(6)), 'link' => 'https://pinterest.com/pin/mock_' . bin2hex(random_bytes(4))];
        }

        $url = self::REST_API_BASE . '/pins';

        $payload = [
            'board_id'    => $boardId,
            'title'       => mb_substr($title, 0, 100),
            'description' => mb_substr($description, 0, 500),
        ];

        if (!empty($linkUrl) && filter_var($linkUrl, FILTER_VALIDATE_URL)) {
            $payload['link'] = $linkUrl;
        }

        // Pinterest requires a media source
        // In local/offline environment, if mediaPath is local file, we format media_source with base64 or public url
        if (!empty($mediaPath) && file_exists($mediaPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $mediaPath);
            finfo_close($finfo);
            $imgData = base64_encode(file_get_contents($mediaPath));
            $payload['media_source'] = [
                'source_type'  => 'image_base64',
                'content_type' => $mime ?: 'image/jpeg',
                'data'         => $imgData,
            ];
        } else {
            // Placeholder standard high-resolution graphic source
            $payload['media_source'] = [
                'source_type' => 'image_url',
                'url'         => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1200&q=80',
            ];
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $res = self::curlRequest($url, 'POST', $payload, true, $headers);

        if (isset($res['error']) || isset($res['code']) && $res['code'] >= 400) {
            $errMsg = $res['message'] ?? ($res['error']['message'] ?? 'Pinterest pin publishing failed.');
            return ['success' => false, 'error' => $errMsg];
        }

        return [
            'success' => true,
            'pin_id'  => (string)($res['id'] ?? 'pin_' . time()),
            'link'    => (string)($res['link'] ?? ''),
        ];
    }

    /**
     * Comment / Reply on a Pin
     */
    public static function commentOnPin(string $pinId, string $accessToken, string $commentText): array
    {
        if (str_starts_with($accessToken, 'mock_')) {
            return ['success' => true, 'comment_id' => 'comment_mock_' . bin2hex(random_bytes(6))];
        }

        $url = self::REST_API_BASE . "/pins/{$pinId}/comments";

        $payload = [
            'text' => $commentText,
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $res = self::curlRequest($url, 'POST', $payload, true, $headers);

        if (isset($res['error']) || isset($res['code']) && $res['code'] >= 400) {
            $errMsg = $res['message'] ?? ($res['error']['message'] ?? 'Pinterest comment failed.');
            return ['success' => false, 'error' => $errMsg];
        }

        return [
            'success'    => true,
            'comment_id' => (string)($res['id'] ?? 'comment_' . time()),
        ];
    }

    /**
     * Native cURL HTTP Client
     */
    private static function curlRequest(string $url, string $method, array $data, bool $isJson = true, array $customHeaders = []): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => 'PHP cURL extension is not enabled.'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        configure_curl_ssl($ch);

        $headers = $customHeaders;

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($isJson) {
                $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $hasContentType = false;
                foreach ($headers as $h) {
                    if (stripos($h, 'Content-Type:') === 0) {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    $headers[] = 'Content-Type: application/json';
                }
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        } elseif ($method === 'GET' && !empty($data)) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            curl_setopt($ch, CURLOPT_URL, $url . $separator . http_build_query($data));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['error' => 'cURL error: ' . $err];
        }

        if (empty($body)) {
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'code' => $httpCode];
            }
            return ['error' => 'Empty response from Pinterest API with HTTP ' . $httpCode];
        }

        $json = json_decode((string)$body, true);
        return is_array($json) ? $json : ['raw' => $body, 'http_code' => $httpCode];
    }
}
