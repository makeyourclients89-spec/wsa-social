<?php
/**
 * Meta Graph API Integration (Facebook Pages & Instagram Professional)
 * Official OAuth 2.0, Auto-Posting & Real-Time Auto-Commenting System
 * Native PHP cURL implementation (Zero external Composer/SDK dependencies)
 */

declare(strict_types=1);

if (!defined('APP_SECURE')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

class MetaGraph
{
    private const GRAPH_API_BASE = 'https://graph.facebook.com/v19.0';
    private const OAUTH_DIALOG_URL = 'https://www.facebook.com/v19.0/dialog/oauth';

    /**
     * Required Meta Scopes for Posting + Commenting:
     * - pages_show_list: list user pages
     * - pages_read_engagement: read engagement & comments
     * - pages_manage_posts: create posts on Facebook Page
     * - pages_manage_engagement: post/reply to comments on Facebook Page
     * - instagram_basic: get Instagram account details & media
     * - instagram_content_publish: auto-publish posts to Instagram
     * - instagram_manage_comments: auto-reply and post comments on Instagram media
     */
    public const ESSENTIAL_SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'instagram_basic',
    ];

    public const FULL_SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'pages_manage_engagement',
        'instagram_basic',
    ];

    public const REQUIRED_SCOPES = self::FULL_SCOPES;

    /**
     * Retrieve Meta App settings from database
     */
    public static function getCredentials(PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT key, value FROM system_settings WHERE key IN ('meta_app_id', 'meta_app_secret', 'meta_redirect_uri')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'app_id' => trim($rows['meta_app_id'] ?? ''),
            'app_secret' => trim($rows['meta_app_secret'] ?? ''),
            'redirect_uri' => trim($rows['meta_redirect_uri'] ?? ''),
        ];
    }

    /**
     * Check if Meta credentials are configured
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
     * Generate Login / Authorization Dialog URL
     */
    public static function getAuthorizationUrl(PDO $pdo, string $state, string $scopeMode = 'essential'): string
    {
        $creds = self::getCredentials($pdo);
        if (empty($creds['app_id'])) {
            return '';
        }

        $redirectUri = self::resolveRedirectUri($creds);

        $scopes = ($scopeMode === 'full') ? self::FULL_SCOPES : self::ESSENTIAL_SCOPES;

        $params = [
            'client_id' => $creds['app_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
        ];

        return self::OAUTH_DIALOG_URL . '?' . http_build_query($params);
    }

    /**
     * Automatically detect OAuth callback URL
     */
    public static function detectRedirectUri(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/wsa-social/index.php';
        
        return $scheme . '://' . $host . $script . '?page=meta-callback';
    }

    /**
     * Exchange OAuth Code for User Access Token, then upgrade to Long-Lived Token
     */
    public static function exchangeCodeForTokens(PDO $pdo, string $code): array
    {
        $creds = self::getCredentials($pdo);
        $redirectUri = self::resolveRedirectUri($creds);

        // Step 1: Exchange code for short-lived access token
        $tokenUrl = self::GRAPH_API_BASE . '/oauth/access_token?' . http_build_query([
            'client_id' => $creds['app_id'],
            'client_secret' => $creds['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $res = self::curlGet($tokenUrl);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Failed to exchange authorization code.'];
        }

        $shortLivedToken = $res['access_token'] ?? '';
        if (empty($shortLivedToken)) {
            return ['success' => false, 'error' => 'No access token received from Meta.'];
        }

        // Step 2: Exchange for Long-Lived User Access Token (~60 days)
        $exchangeUrl = self::GRAPH_API_BASE . '/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $creds['app_id'],
            'client_secret' => $creds['app_secret'],
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $longRes = self::curlGet($exchangeUrl);
        $userAccessToken = $longRes['access_token'] ?? $shortLivedToken;
        $expiresIn = (int)($longRes['expires_in'] ?? 5184000); // Default ~60 days
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Step 3: Fetch User Pages with connected Instagram Business Accounts
        $pagesUrl = self::GRAPH_API_BASE . '/me/accounts?' . http_build_query([
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name}',
            'access_token' => $userAccessToken,
        ]);

        $pagesRes = self::curlGet($pagesUrl);
        if (isset($pagesRes['error'])) {
            return ['success' => false, 'error' => $pagesRes['error']['message'] ?? 'Could not retrieve Facebook pages.'];
        }

        $pages = $pagesRes['data'] ?? [];
        return [
            'success' => true,
            'pages' => $pages,
            'user_token' => $userAccessToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Direct Token Sync & Long-Lived Upgrade using configured App ID & Secret
     * Works for both User Access Tokens (fetches all managed pages) and direct Page Access Tokens.
     */
    public static function syncWithDirectToken(PDO $pdo, string $rawToken): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return ['success' => false, 'error' => 'Access token cannot be empty.'];
        }

        $creds = self::getCredentials($pdo);
        $userAccessToken = $rawToken;
        $expiresAt = date('Y-m-d H:i:s', time() + 5184000);

        // Upgrade to Long-Lived Token using App ID & Secret if configured
        if (!empty($creds['app_id']) && !empty($creds['app_secret'])) {
            $exchangeUrl = self::GRAPH_API_BASE . '/oauth/access_token?' . http_build_query([
                'grant_type' => 'fb_exchange_token',
                'client_id' => $creds['app_id'],
                'client_secret' => $creds['app_secret'],
                'fb_exchange_token' => $rawToken,
            ]);
            $longRes = self::curlGet($exchangeUrl);
            if (!empty($longRes['access_token'])) {
                $userAccessToken = (string)$longRes['access_token'];
                $expiresIn = (int)($longRes['expires_in'] ?? 5184000);
                $expiresAt = date('Y-m-d H:i:s', time() + ($expiresIn > 0 ? $expiresIn : 5184000));
            }
        }

        // Try fetching managed pages (/me/accounts)
        $pagesUrl = self::GRAPH_API_BASE . '/me/accounts?' . http_build_query([
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name}',
            'limit' => 100,
            'access_token' => $userAccessToken,
        ]);

        $pagesRes = self::curlGet($pagesUrl);
        if (!empty($pagesRes['data']) && is_array($pagesRes['data'])) {
            return [
                'success' => true,
                'pages' => $pagesRes['data'],
                'user_token' => $userAccessToken,
                'expires_at' => $expiresAt,
            ];
        }

        // Fallback: If token is a direct Page Access Token, query /me directly
        $meUrl = self::GRAPH_API_BASE . '/me?' . http_build_query([
            'fields' => 'id,name,instagram_business_account{id,username,name}',
            'access_token' => $userAccessToken,
        ]);
        $meRes = self::curlGet($meUrl);
        if (isset($meRes['error'])) {
            return ['success' => false, 'error' => $meRes['error']['message'] ?? 'Invalid Meta Access Token.'];
        }

        if (!empty($meRes['id'])) {
            $singlePage = [
                'id' => (string)$meRes['id'],
                'name' => (string)($meRes['name'] ?? 'Facebook Page'),
                'access_token' => $userAccessToken,
            ];
            if (!empty($meRes['instagram_business_account'])) {
                $singlePage['instagram_business_account'] = $meRes['instagram_business_account'];
            }
            return [
                'success' => true,
                'pages' => [$singlePage],
                'user_token' => $userAccessToken,
                'expires_at' => $expiresAt,
            ];
        }

        return ['success' => false, 'error' => 'No Facebook Pages found for this token.'];
    }

    /**
     * Fetch user groups from Meta Graph API
     */
    public static function fetchUserGroups(string $userToken, ?string $userId = null): array
    {
        $node = $userId ?: 'me';
        $url = self::GRAPH_API_BASE . '/' . urlencode($node) . '/groups?' . http_build_query([
            'fields' => 'id,name,privacy,member_count',
            'limit' => 200,
            'access_token' => $userToken,
        ]);
        $res = self::curlGet($url);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Graph API error'];
        }
        return ['success' => true, 'groups' => $res['data'] ?? []];
    }

    /**
     * Fetch the most recent posts or reels published on a Facebook Page
     */
    public static function fetchLatestPagePosts(string $pageId, string $pageToken, int $limit = 5): array
    {
        $url = self::GRAPH_API_BASE . '/' . urlencode($pageId) . '/posts?' . http_build_query([
            'fields' => 'id,message,permalink_url,created_time,status_type,full_picture',
            'limit' => $limit,
            'access_token' => $pageToken,
        ]);
        $res = self::curlGet($url);
        if (isset($res['error']) || empty($res['data'])) {
            return [];
        }
        return $res['data'];
    }

    /**
     * Inspect and retrieve user profile details from a live User Access Token
     */
    public static function inspectUserProfile(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'Please provide a valid Meta Access Token.'];
        }

        $url = self::GRAPH_API_BASE . '/me?' . http_build_query([
            'fields' => 'id,name,picture.width(150).height(150)',
            'access_token' => $token,
        ]);

        $res = self::curlGet($url);
        if (isset($res['error'])) {
            return [
                'success' => false,
                'error' => $res['error']['message'] ?? 'Invalid Meta Access Token or expired session.',
            ];
        }

        if (empty($res['id'])) {
            return ['success' => false, 'error' => 'Could not retrieve Facebook profile details.'];
        }

        return [
            'success' => true,
            'id' => (string)$res['id'],
            'name' => (string)($res['name'] ?? 'Facebook User'),
            'avatar_url' => (string)($res['picture']['data']['url'] ?? ''),
        ];
    }

    /**
     * Inspect token permissions using configured App ID & Secret
     */
    public static function inspectTokenScopes(PDO $pdo, string $token): array
    {
        $creds = self::getCredentials($pdo);
        if (empty($creds['app_id']) || empty($creds['app_secret']) || empty($token)) {
            return ['is_valid' => false, 'scopes' => [], 'has_post_perm' => false];
        }

        $debugUrl = self::GRAPH_API_BASE . '/debug_token?' . http_build_query([
            'input_token' => $token,
            'access_token' => $creds['app_id'] . '|' . $creds['app_secret'],
        ]);

        $res = self::curlGet($debugUrl);
        $data = $res['data'] ?? [];
        $scopes = $data['scopes'] ?? [];
        $isValid = !empty($data['is_valid']);
        $hasPostPerm = in_array('pages_manage_posts', $scopes, true);

        return [
            'is_valid' => $isValid,
            'scopes' => $scopes,
            'has_post_perm' => $hasPostPerm,
            'app_id' => $data['app_id'] ?? '',
            'user_id' => $data['user_id'] ?? '',
        ];
    }

    /**
     * Publish a post or photo to a Facebook Page
     */
    public static function publishToFacebookPage(string $pageId, string $pageToken, string $message, ?string $linkUrl = null, ?string $mediaPath = null): array
    {
        if (str_starts_with($pageToken, 'mock_')) {
            return ['success' => true, 'post_id' => 'fb_' . $pageId . '_' . bin2hex(random_bytes(6))];
        }

        // Direct photo upload if image media is attached
        if (!empty($mediaPath)) {
            $absPath = realpath($mediaPath) ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($mediaPath, '/\\'));
            if (file_exists($absPath)) {
                $endpoint = self::GRAPH_API_BASE . '/' . urlencode($pageId) . '/photos';
                $caption = $message;
                if (!empty($linkUrl) && stripos($message, $linkUrl) === false) {
                    $caption .= "\n\n🔗 " . $linkUrl;
                }
                $params = [
                    'access_token' => $pageToken,
                    'caption' => $caption,
                    'source' => new CURLFile($absPath),
                ];
                $res = self::curlPost($endpoint, $params);
                if (isset($res['error'])) {
                    return ['success' => false, 'error' => $res['error']['message'] ?? 'Facebook photo publishing failed.'];
                }
                return ['success' => true, 'post_id' => $res['post_id'] ?? ($res['id'] ?? 'unknown')];
            }
        }

        $endpoint = self::GRAPH_API_BASE . '/' . urlencode($pageId) . '/feed';
        $params = [
            'access_token' => $pageToken,
            'message' => $message,
        ];

        if (!empty($linkUrl)) {
            $params['link'] = $linkUrl;
        }

        $res = self::curlPost($endpoint, $params);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Facebook publishing failed.'];
        }

        return ['success' => true, 'post_id' => $res['id'] ?? 'unknown'];
    }

    /**
     * Publish a post to a Facebook Group
     */
    public static function publishToFacebookGroup(string $groupSlug, string $pageToken, string $message, ?string $linkUrl = null): array
    {
        if (str_starts_with($pageToken, 'mock_')) {
            return ['success' => true, 'post_id' => 'fb_grp_post_' . bin2hex(random_bytes(6)), 'post_url' => 'https://www.facebook.com/groups/' . $groupSlug];
        }

        $endpoint = self::GRAPH_API_BASE . '/' . urlencode($groupSlug) . '/feed';
        $params = [
            'access_token' => $pageToken,
            'message' => $message,
        ];

        if (!empty($linkUrl)) {
            $params['link'] = $linkUrl;
        }

        $res = self::curlPost($endpoint, $params);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Group publishing failed.'];
        }

        $postId = (string)($res['id'] ?? '');
        $postUrl = !empty($postId) ? ('https://www.facebook.com/' . $postId) : ('https://www.facebook.com/groups/' . $groupSlug);

        return ['success' => true, 'post_id' => $postId, 'post_url' => $postUrl];
    }

    /**
     * Post a comment on a Facebook Page Post or Photo
     */
    public static function commentOnFacebookPost(string $postOrCommentId, string $pageToken, string $commentMessage): array
    {
        if (str_starts_with($pageToken, 'mock_')) {
            return ['success' => true, 'comment_id' => 'fb_comm_' . bin2hex(random_bytes(6))];
        }

        $endpoint = self::GRAPH_API_BASE . '/' . urlencode($postOrCommentId) . '/comments';
        $params = [
            'access_token' => $pageToken,
            'message' => $commentMessage,
        ];

        $res = self::curlPost($endpoint, $params);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Facebook commenting failed.'];
        }

        return ['success' => true, 'comment_id' => $res['id'] ?? 'unknown'];
    }

    /**
     * Like a Facebook Page Post or Photo from a Page
     */
    public static function likeFacebookPost(string $postOrCommentId, string $pageToken): array
    {
        if (str_starts_with($pageToken, 'mock_')) {
            return ['success' => true];
        }

        $endpoint = self::GRAPH_API_BASE . '/' . urlencode($postOrCommentId) . '/likes';
        $params = [
            'access_token' => $pageToken,
        ];

        $res = self::curlPost($endpoint, $params);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Facebook like failed.'];
        }

        return ['success' => true];
    }

    /**
     * Publish a photo post to Instagram Professional Account
     * (Instagram requires a public HTTPS URL)
     */
    public static function publishToInstagram(string $igUserId, string $accessToken, string $imageUrl, string $caption): array
    {
        if (str_starts_with($accessToken, 'mock_')) {
            return ['success' => true, 'media_id' => 'ig_' . $igUserId . '_' . bin2hex(random_bytes(6))];
        }

        // Step 1: Create media container
        $containerUrl = self::GRAPH_API_BASE . '/' . urlencode($igUserId) . '/media';
        $containerParams = [
            'access_token' => $accessToken,
            'image_url' => $imageUrl,
            'caption' => $caption,
        ];

        $cRes = self::curlPost($containerUrl, $containerParams);
        if (isset($cRes['error'])) {
            return ['success' => false, 'error' => $cRes['error']['message'] ?? 'Instagram media container creation failed.'];
        }

        $containerId = $cRes['id'] ?? null;
        if (!$containerId) {
            return ['success' => false, 'error' => 'Instagram container ID not returned.'];
        }

        // Brief delay for Instagram container readiness
        sleep(2);

        // Step 2: Publish media container
        $publishUrl = self::GRAPH_API_BASE . '/' . urlencode($igUserId) . '/media_publish';
        $publishParams = [
            'access_token' => $accessToken,
            'creation_id' => $containerId,
        ];

        $pRes = self::curlPost($publishUrl, $publishParams);
        if (isset($pRes['error'])) {
            return ['success' => false, 'error' => $pRes['error']['message'] ?? 'Instagram media publish failed.'];
        }

        return ['success' => true, 'media_id' => $pRes['id'] ?? 'unknown'];
    }

    /**
     * Post a comment or reply on an Instagram Media object
     */
    public static function commentOnInstagramMedia(string $mediaId, string $accessToken, string $commentText): array
    {
        if (str_starts_with($accessToken, 'mock_')) {
            return ['success' => true, 'comment_id' => 'ig_comm_' . bin2hex(random_bytes(6))];
        }

        $endpoint = self::GRAPH_API_BASE . '/' . urlencode($mediaId) . '/comments';
        $params = [
            'access_token' => $accessToken,
            'message' => $commentText,
        ];

        $res = self::curlPost($endpoint, $params);
        if (isset($res['error'])) {
            return ['success' => false, 'error' => $res['error']['message'] ?? 'Instagram commenting failed.'];
        }

        return ['success' => true, 'comment_id' => $res['id'] ?? 'unknown'];
    }

    /**
     * Native HTTP GET with cURL
     */
    private static function curlGet(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => ['message' => 'PHP cURL extension is not enabled.']];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        configure_curl_ssl($ch);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => ['message' => 'cURL error: ' . $err]];
        }

        $data = json_decode((string)$body, true);
        return is_array($data) ? $data : ['error' => ['message' => 'Invalid JSON from Meta API.']];
    }

    /**
     * Native HTTP POST with cURL
     */
    private static function curlPost(string $url, array $params): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => ['message' => 'PHP cURL extension is not enabled.']];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $hasFile = false;
        foreach ($params as $v) {
            if ($v instanceof CURLFile) {
                $hasFile = true;
                break;
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $params : http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        configure_curl_ssl($ch);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => ['message' => 'cURL error: ' . $err]];
        }

        $data = json_decode((string)$body, true);
        return is_array($data) ? $data : ['error' => ['message' => 'Invalid JSON from Meta API.']];
    }
}
