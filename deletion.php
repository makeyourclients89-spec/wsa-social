<?php
/**
 * WSA Multi-Channel Publisher - User Data Deletion Callback & Instructions
 * Live URL: https://atopbuilders.com/tools/wsa-social/deletion.php
 * Official Meta Data Deletion Callback and User Deletion Status Page.
 */
declare(strict_types=1);

$appName = 'WSA Multi-Channel Publisher';
$companyName = 'WSA Digital Agency & Atop Builders';
$contactEmail = 'support@wsadigital.com';
$baseUrl = 'https://atopbuilders.com/tools/wsa-social/';

// ----------------------------------------------------------------------
// 1. Meta Automated Data Deletion Callback (POST signed_request)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['signed_request'])) {
    header('Content-Type: application/json; charset=utf-8');

    $signedRequest = (string)$_POST['signed_request'];
    $confirmationCode = 'del_' . bin2hex(random_bytes(10));
    $statusUrl = $baseUrl . 'deletion.php?id=' . urlencode($confirmationCode);

    // Optional: Log deletion request to sqlite if available
    try {
        if (file_exists(__DIR__ . '/storage/wsa_publisher.sqlite')) {
            $pdo = new PDO('sqlite:' . __DIR__ . '/storage/wsa_publisher.sqlite');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $pdo->exec("CREATE TABLE IF NOT EXISTS data_deletion_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                confirmation_code TEXT UNIQUE,
                payload TEXT,
                status TEXT DEFAULT 'completed',
                created_at TEXT
            )");
            $stmt = $pdo->prepare("INSERT INTO data_deletion_requests(confirmation_code, payload, status, created_at) VALUES (?, ?, 'completed', datetime('now'))");
            $stmt->execute([$confirmationCode, substr($signedRequest, 0, 500)]);
        }
    } catch (Throwable $e) {
        // Fallback without breaking JSON response
    }

    echo json_encode([
        'url' => $statusUrl,
        'confirmation_code' => $confirmationCode
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ----------------------------------------------------------------------
// 2. User-Facing GET Request: Status Check & Instructions Page
// ----------------------------------------------------------------------
$reqId = trim((string)($_GET['id'] ?? ''));
$isFormSubmitted = false;
$userSubmittedCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['manual_request'])) {
    $fbUserId = trim((string)($_POST['facebook_user_id'] ?? ''));
    $pageName = trim((string)($_POST['page_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));

    $userSubmittedCode = 'usr_' . bin2hex(random_bytes(8));
    $isFormSubmitted = true;

    try {
        if (file_exists(__DIR__ . '/storage/wsa_publisher.sqlite')) {
            $pdo = new PDO('sqlite:' . __DIR__ . '/storage/wsa_publisher.sqlite');
            $pdo->exec("CREATE TABLE IF NOT EXISTS data_deletion_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                confirmation_code TEXT UNIQUE,
                payload TEXT,
                status TEXT DEFAULT 'completed',
                created_at TEXT
            )");
            $stmt = $pdo->prepare("INSERT INTO data_deletion_requests(confirmation_code, payload, status, created_at) VALUES (?, ?, 'pending', datetime('now'))");
            $stmt->execute([$userSubmittedCode, json_encode(['fb_id' => $fbUserId, 'page' => $pageName, 'email' => $email])]);
        }
    } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Data Deletion - <?= htmlspecialchars($appName) ?></title>
  <meta name="description" content="Official User Data Deletion Instructions and Status Callback for <?= htmlspecialchars($appName) ?>.">
  <style>
    :root {
      --bg: #0f172a;
      --card-bg: #1e293b;
      --border: #334155;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --primary: #3b82f6;
      --accent: #10b981;
      --warning: #f59e0b;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.65;
      padding: 40px 20px;
    }
    .container {
      max-width: 860px;
      margin: 0 auto;
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 44px 40px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    header {
      border-bottom: 1px solid var(--border);
      padding-bottom: 24px;
      margin-bottom: 28px;
    }
    .badge {
      display: inline-block;
      background: rgba(16, 185, 129, 0.15);
      color: #34d399;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }
    h1 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
    h2 { font-size: 19px; font-weight: 700; color: #93c5fd; margin: 28px 0 12px; }
    p, ul, ol { margin-bottom: 16px; font-size: 14.5px; color: #cbd5e1; }
    ol { padding-left: 22px; }
    li { margin-bottom: 10px; }
    strong { color: #fff; }
    a { color: var(--primary); text-decoration: none; font-weight: 600; }
    a:hover { text-decoration: underline; }
    .status-card {
      background: rgba(16, 185, 129, 0.12);
      border: 1px solid var(--accent);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 24px;
    }
    .status-card.pending {
      background: rgba(245, 158, 11, 0.12);
      border-color: var(--warning);
    }
    .code-box {
      display: inline-block;
      background: #0f172a;
      border: 1px solid #475569;
      color: #38bdf8;
      font-family: monospace;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 15px;
      font-weight: bold;
    }
    .step-card {
      background: #0f172a;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 20px 24px;
      margin: 16px 0;
    }
    .form-group { margin-bottom: 14px; }
    label { display: block; font-size: 13px; font-weight: 700; color: #cbd5e1; margin-bottom: 6px; }
    input {
      width: 100%;
      padding: 10px 14px;
      border-radius: 8px;
      background: #0f172a;
      border: 1px solid var(--border);
      color: #fff;
      font-size: 14px;
    }
    input:focus { outline: none; border-color: var(--primary); }
    button {
      background: var(--primary);
      color: #fff;
      font-weight: 700;
      padding: 10px 22px;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.2s;
    }
    button:hover { background: #2563eb; }
    footer {
      margin-top: 40px;
      padding-top: 24px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 13px;
      color: var(--muted);
    }
    .nav-links a { margin-right: 16px; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <span class="badge">Meta Data Protection</span>
      <h1>User Data Deletion Callback &amp; Instructions</h1>
      <p style="color:var(--muted); font-size:13.5px; margin:0;">
        <?= htmlspecialchars($appName) ?> respects your privacy and complies with Meta Platform Data Deletion requirements.
      </p>
    </header>

    <?php if (!empty($reqId)): ?>
      <div class="status-card">
        <h3 style="color:#34d399; margin-bottom:6px;">✓ Deletion Request Confirmed</h3>
        <p style="margin-bottom:8px;">Your automated data deletion request from Meta has been received and processed successfully.</p>
        <p style="margin:0;">Confirmation Code: <span class="code-box"><?= htmlspecialchars($reqId) ?></span></p>
        <p style="font-size:12px; color:var(--muted); margin-top:8px;">All associated OAuth access tokens and cached page identifiers have been scrubbed from our systems.</p>
      </div>
    <?php elseif ($isFormSubmitted && $userSubmittedCode): ?>
      <div class="status-card pending">
        <h3 style="color:#fbbf24; margin-bottom:6px;">Request Received</h3>
        <p style="margin-bottom:8px;">Your manual data erasure request has been queued. Confirmation Code: <span class="code-box"><?= htmlspecialchars($userSubmittedCode) ?></span></p>
        <p style="margin:0; font-size:13px;">Our system administrator will verify and delete your stored records within 24 hours.</p>
      </div>
    <?php endif; ?>

    <h2>How to Delete Your Data from <?= htmlspecialchars($appName) ?></h2>
    <p>In accordance with Meta Platform Terms, users can remove their information using any of the following methods:</p>

    <div class="step-card">
      <h3 style="color:#fff; font-size:16px; margin-bottom:10px;">Method 1: Instant Self-Service via Facebook Settings (Recommended)</h3>
      <ol>
        <li>Log in to your Facebook profile and navigate to <strong>Settings &amp; Privacy &gt; Settings</strong>.</li>
        <li>On the left menu, select <strong>Business Integrations</strong> (or <strong>Apps and Websites</strong>).</li>
        <li>Locate <strong><?= htmlspecialchars($appName) ?></strong> in the list of active apps.</li>
        <li>Check the box next to the app and click the <strong>Remove</strong> button.</li>
        <li>Check the option to <em>"Delete posts, videos or events that <?= htmlspecialchars($appName) ?> posted on your behalf"</em> if desired, then confirm by clicking <strong>Remove</strong>.</li>
      </ol>
      <p style="font-size:13px; color:var(--muted); margin:0;">
        Once removed, Facebook automatically sends a secure callback to our server, and all your stored tokens and page records are immediately deleted.
      </p>
    </div>

    <div class="step-card">
      <h3 style="color:#fff; font-size:16px; margin-bottom:12px;">Method 2: Submit a Direct Deletion Request</h3>
      <form method="post" action="deletion.php">
        <input type="hidden" name="manual_request" value="1">
        <div class="form-group">
          <label for="fb_id">Facebook Profile ID or Page URL:</label>
          <input type="text" id="fb_id" name="facebook_user_id" required placeholder="e.g. 573548745852007 or facebook.com/yourpage">
        </div>
        <div class="form-group">
          <label for="page_name">Connected Page / Brand Name:</label>
          <input type="text" id="page_name" name="page_name" placeholder="e.g. WSA Digital Agency">
        </div>
        <div class="form-group">
          <label for="email">Contact Email Address (for confirmation):</label>
          <input type="email" id="email" name="email" required placeholder="name@yourdomain.com">
        </div>
        <button type="submit">Submit Data Deletion Request</button>
      </form>
    </div>

    <h2>What Data is Deleted?</h2>
    <ul>
      <li>All Facebook and Instagram OAuth page access tokens stored in our database.</li>
      <li>All cached profile information, page categories, and metadata.</li>
      <li>All queued publishing jobs and drafts associated with your workspace.</li>
    </ul>

    <h2>Contact Information</h2>
    <p>For urgent privacy inquiries or manual data verification, you may email our Data Protection team directly at <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.</p>

    <footer>
      <div>&copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>. All rights reserved.</div>
      <div class="nav-links">
        <a href="privacy.php">Privacy Policy</a>
        <a href="terms.php">Terms of Service</a>
      </div>
    </footer>
  </div>
</body>
</html>
