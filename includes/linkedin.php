<?php
/**
 * LinkedIn Marketing & Community Management API Integration
 * Official OAuth 2.0 (OpenID Connect / Share on LinkedIn / Member Posts), Auto-Posting & Auto-Commenting
 * Native PHP cURL implementation (Zero external Composer/SDK dependencies)
 */

declare(strict_types=1);

if (!defined('APP_SECURE')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

class LinkedInClient
{
    private const OAUTH_DIALOG_URL = 'https://www.linkedin.com/oauth/v2/authorization';
    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';
    private const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';
    private const REST_API_BASE = 'https://api.linkedin.com/rest';

    /**
     * LinkedIn Scopes:
     * - openid, profile, email: user identity and URN sub
     * - w_member_social: publish shares, posts, and comments on behalf of the member
     */
    public const REQUIRED_SCOPES = [
        'openid',
        'profile',
        'email',
        'w_member_social',
    ];

    /**
     * Retrieve LinkedIn settings from database
     */
    public static function getCredentials(PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT key, value FROM system_settings WHERE key IN ('linkedin_client_id', 'linkedin_client_secret', 'linkedin_redirect_uri')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'client_id' => trim($rows['linkedin_client_id'] ?? ''),
            'client_secret' => trim($rows['linkedin_client_secret'] ?? ''),
            'redirect_uri' => trim($rows['linkedin_redirect_uri'] ?? ''),
        ];
    }

    /**
     * Check if LinkedIn App is configured
     */
    public static function isConfigured(PDO $pdo): bool
    {
        $creds = self::getCredentials($pdo);
        return !empty($creds['client_id']) && !empty($creds['client_secret']);
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

        return $scheme . '://' . $host . $script . '?page=linkedin-callback';
    }

    /**
     * Build LinkedIn OAuth 2.0 Authorization URL
     */
    public static function getAuthorizationUrl(PDO $pdo, string $state): string
    {
        $creds = self::getCredentials($pdo);
        if (empty($creds['client_id'])) {
            return '';
        }

        $redirectUri = self::resolveRedirectUri($creds);

        $params = [
            'response_type' => 'code',
            'client_id'     => $creds['client_id'],
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => implode(' ', self::REQUIRED_SCOPES),
        ];

        return self::OAUTH_DIALOG_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange Authorization Code for Access Token and fetch Member Profile URN
     */
    public static function exchangeCodeForTokens(PDO $pdo, string $code): array
    {
        $creds = self::getCredentials($pdo);
        $redirectUri = self::resolveRedirectUri($creds);

        // 1. Exchange code for Access Token
        $tokenPostData = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
        ];

        $tokenRes = self::curlRequest(self::TOKEN_URL, 'POST', $tokenPostData, false);
        if (isset($tokenRes['error'])) {
            return ['success' => false, 'error' => $tokenRes['error_description'] ?? ($tokenRes['error'] ?? 'LinkedIn token exchange failed.')];
        }

        $accessToken = (string)($tokenRes['access_token'] ?? '');
        $refreshToken = (string)($tokenRes['refresh_token'] ?? '');
        $expiresIn = (int)($tokenRes['expires_in'] ?? 5184000); // ~60 days
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'No access token received from LinkedIn.'];
        }

        // 2. Fetch User Profile Info (OpenID Connect userinfo)
        $userHeaders = [
            'Authorization: Bearer ' . $accessToken,
        ];
        $userRes = self::curlRequest(self::USERINFO_URL, 'GET', [], true, $userHeaders);

        $memberSub = (string)($userRes['sub'] ?? '');
        $memberName = (string)($userRes['name'] ?? 'LinkedIn Member');
        $urn = !empty($memberSub) ? 'urn:li:person:' . $memberSub : '';

        return [
            'success'       => true,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $expiresAt,
            'urn'           => $urn,
            'name'          => $memberName,
        ];
    }

    /**
     * Create / Publish a Post on LinkedIn (Member Share via Posts API)
     */
    public static function publishPost(string $authorUrn, string $accessToken, string $commentary, ?string $linkUrl = null): array
    {
        if (str_starts_with($accessToken, 'mock_')) {
            return ['success' => true, 'post_id' => 'urn:li:share:mock_' . bin2hex(random_bytes(6))];
        }

        $url = self::REST_API_BASE . '/posts';

        $payload = [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        if (!empty($linkUrl)) {
            $payload['content'] = [
                'article' => [
                    'source' => $linkUrl,
                    'title' => 'Shared Link',
                ],
            ];
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: 202401',
        ];

        $res = self::curlRequest($url, 'POST', $payload, true, $headers);

        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? ($res['message'] ?? 'LinkedIn post creation failed.')];
        }

        $postId = $res['id'] ?? ($res['x-restli-id'] ?? 'published_post');
        return ['success' => true, 'post_id' => $postId];
    }

    /**
     * Post a comment on a LinkedIn Post
     * URN format: urn:li:share:123 or urn:li:ugcPost:123
     */
    public static function commentOnPost(string $postUrn, string $actorUrn, string $accessToken, string $commentText): array
    {
        if (str_starts_with($accessToken, 'mock_')) {
            return ['success' => true, 'comment_id' => 'urn:li:comment:mock_' . bin2hex(random_bytes(6))];
        }

        $encodedPostUrn = urlencode($postUrn);
        $url = self::REST_API_BASE . "/socialActions/{$encodedPostUrn}/comments";

        $payload = [
            'actor' => $actorUrn,
            'message' => [
                'text' => $commentText,
            ],
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: 202401',
        ];

        $res = self::curlRequest($url, 'POST', $payload, true, $headers);

        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? ($res['message'] ?? 'LinkedIn commenting failed.')];
        }

        return ['success' => true, 'comment_id' => $res['id'] ?? 'unknown'];
    }

    /**
     * Universal Native cURL request handler
     */
    private static function curlRequest(string $url, string $method, array $data, bool $isJson = true, array $customHeaders = []): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => ['message' => 'PHP cURL extension is not enabled.']];
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
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['error' => ['message' => 'cURL error: ' . $err]];
        }

        if (empty($body)) {
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'code' => $httpCode];
            }
            return ['error' => ['message' => 'Empty response from LinkedIn API with HTTP ' . $httpCode]];
        }

        $json = json_decode((string)$body, true);
        return is_array($json) ? $json : ['raw' => $body];
    }
}
