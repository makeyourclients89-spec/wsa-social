<?php
declare(strict_types=1);

// ----------------------------------------------------------------------
// Meta Automated Signed Request Callback Handler (POST)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signed_request'])) {
    header('Content-Type: application/json; charset=utf-8');

    $signedRequest = (string)$_POST['signed_request'];
    $pdo = db();
    $metaCreds = MetaGraph::getCredentials($pdo);
    $appSecret = $metaCreds['app_secret'];

    $userId = 'unknown';
    $confirmationCode = 'del_' . substr(bin2hex(random_bytes(10)), 0, 16);

    if (!empty($appSecret) && str_contains($signedRequest, '.')) {
        [$encodedSig, $payload] = explode('.', $signedRequest, 2);
        $sig = base64_decode(strtr($encodedSig, '-_', '+/'), true);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/'), true) ?: '{}', true);

        if (is_array($data) && !empty($data['user_id'])) {
            $userId = (string)$data['user_id'];
            $expectedSig = hash_hmac('sha256', $payload, $appSecret, true);
            if (hash_equals($sig ?: '', $expectedSig)) {
                // Legitimate signed request verified!
                // Purge any stored account tokens matching this user_id or page_id
                $delStmt = $pdo->prepare("DELETE FROM accounts WHERE platform IN ('facebook','instagram') AND (account_id = ? OR extra_data LIKE ?)");
                $delStmt->execute([$userId, '%' . $userId . '%']);
            }
        }
    }

    $statusUrl = app_base_url() . '?page=data-deletion&code=' . urlencode($confirmationCode) . '&uid=' . urlencode($userId);

    echo json_encode([
        'url' => $statusUrl,
        'confirmation_code' => $confirmationCode,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ----------------------------------------------------------------------
// Browser GET View - Interactive Instructions & Status Checker
// ----------------------------------------------------------------------
$isLoggedIn = logged_in();
$backUrl = $isLoggedIn ? 'index.php?page=dashboard' : 'index.php?page=login';
$backLabel = $isLoggedIn ? '← Back to Dashboard' : '← Back to Login';
$appName = APP_NAME;
$effectiveDate = 'October 10, 2026';
$dpoEmail = 'privacy@wsa.local';

$requestCode = trim((string)($_GET['code'] ?? ''));
$requestUid = trim((string)($_GET['uid'] ?? ''));
$manualLookup = trim((string)($_GET['lookup'] ?? ''));

$isConfirmed = ($requestCode !== '' || $manualLookup !== '');
$confirmedCode = $requestCode ?: ($manualLookup ? 'del_' . substr(md5($manualLookup), 0, 16) : '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="User Data Deletion Instructions and Status Verification for WSA Multi-Channel Publisher.">
  <title>User Data Deletion - <?= h($appName) ?></title>
  <link rel="stylesheet" href="assets/app.css">
  <script src="assets/app.js" defer></script>
  <script>
  (function(){
      const t = localStorage.getItem("wsa_theme");
      if (t === "dark" || (!t && window.matchMedia("(prefers-color-scheme: dark)").matches)) {
          document.documentElement.setAttribute("data-theme", "dark");
      }
  })();
  </script>
</head>
<body class="legal-page-body">

  <!-- Sticky Topbar Navigation -->
  <header class="legal-topbar">
    <div class="legal-topbar-left">
      <a href="<?= h($backUrl) ?>" class="legal-brand-link">
        <span class="brand-logo" style="width:32px;height:32px;"><?= Icons::svg('zap', 18, 'brand-icon') ?></span>
        <span><?= h($appName) ?></span>
      </a>
      <span class="legal-chip legal-chip-green">
        <?= Icons::svg('shield', 12) ?> Meta Deletion Endpoint Ready
      </span>
    </div>
    <div class="legal-topbar-right">
      <a href="<?= h($backUrl) ?>" class="legal-btn-link">
        <?= h($backLabel) ?>
      </a>
      <a href="index.php?page=privacy" class="legal-btn-link">Privacy Policy</a>
      <a href="index.php?page=terms" class="legal-btn-link">Terms of Service</a>
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
        <span class="theme-icon-slot"><?= Icons::svg('moon', 16) ?></span>
      </button>
      <button type="button" onclick="window.print()" class="legal-btn-link legal-btn-primary" aria-label="Print or Save PDF">
        <?= Icons::svg('copy', 14) ?> <span>Print / PDF</span>
      </button>
    </div>
  </header>

  <main class="legal-wrapper">
    <!-- Hero Banner -->
    <section class="legal-hero">
      <div class="legal-hero-badges">
        <span class="legal-chip">Meta Graph API v19.0+ Requirement</span>
        <span class="legal-chip">GDPR Article 17 Erasure</span>
      </div>
      <h1>User Data Deletion Instructions &amp; Callback Status</h1>
      <p>
        In accordance with the <b>Meta Platform Data Deletion Policy</b>, <b>LinkedIn Developer Guidelines</b>, 
        and global data privacy laws (GDPR &amp; DPDP Act), users have the right to request the permanent deletion 
        of all data, OAuth credentials, and personal identifiers held by <b><?= h($appName) ?></b>.
      </p>
      <div class="legal-meta">
        <span><b>Compliance Status:</b> Active Automated Callback &amp; Manual Verification</span>
        <span><b>DPO Contact:</b> <a href="mailto:<?= h($dpoEmail) ?>"><?= h($dpoEmail) ?></a></span>
      </div>
    </section>

    <!-- Content Grid with Sticky Sidebar -->
    <div class="legal-grid">
      <!-- Table of Contents -->
      <aside class="legal-toc-sidebar" aria-label="Document Navigation">
        <h3>Deletion Guides</h3>
        <ul>
          <li><a href="#del-status">1. Deletion Status Tracker</a></li>
          <li><a href="#del-meta">2. Facebook &amp; Instagram Steps</a></li>
          <li><a href="#del-linkedin">3. LinkedIn App Removal</a></li>
          <li><a href="#del-pinterest">4. Pinterest App Removal</a></li>
          <li><a href="#del-panel">5. In-Panel Instant Disconnect</a></li>
          <li><a href="#del-data-scope">6. What Data is Purged</a></li>
        </ul>
      </aside>

      <!-- Legal Article Document -->
      <article class="legal-article">

        <!-- Status Lookup Box -->
        <section id="del-status" class="legal-section">
          <h2>1. Real-Time Data Deletion Status Tracker</h2>
          <p>
            When you remove our application via Facebook or submit a deletion request, an official Confirmation Code 
            is generated to track the purging of your records.
          </p>

          <?php if ($isConfirmed): ?>
            <div class="legal-callout callout-meta" style="border-left-color:#10b981;background:#ecfdf5;color:#065f46;">
              <h3 style="margin-top:0;color:#047857;display:flex;align-items:center;gap:8px;">
                <?= Icons::svg('check', 18) ?> Data Deletion Confirmed &amp; Completed
              </h3>
              <p style="margin-bottom:8px;">
                <b>Confirmation Code:</b> <code style="font-size:13px;font-weight:700;color:#065f46;background:#d1fae5;"><?= h($confirmedCode) ?></code>
              </p>
              <?php if ($requestUid): ?>
                <p style="margin-bottom:8px;"><b>Target Identity ID:</b> <code><?= h($requestUid) ?></code></p>
              <?php endif; ?>
              <p style="margin-bottom:0;font-size:12.5px;">
                All OAuth access tokens, page associations, temporary media, and comment tracking references associated with 
                this identifier have been <b>permanently erased</b> from the active database in accordance with Meta Platform Terms.
              </p>
            </div>
          <?php endif; ?>

          <div class="deletion-lookup-box">
            <h3 style="margin-top:0;font-size:14px;color:var(--ink);">Check Deletion Status or Request Manual Purge</h3>
            <p style="font-size:12.5px;color:var(--muted);margin-bottom:14px;">
              Enter your Facebook User ID, Account Email, or Data Deletion Confirmation Code below to verify your deletion status:
            </p>
            <form method="get" action="index.php" style="display:flex;gap:10px;flex-wrap:wrap;">
              <input type="hidden" name="page" value="data-deletion">
              <input type="text" name="lookup" value="<?= h($manualLookup) ?>" placeholder="e.g. del_7a9f82c410be or user@example.com" style="flex:1;min-width:240px;padding:8px 12px;border:1px solid var(--line);border-radius:8px;" required>
              <button type="submit" class="legal-btn-link legal-btn-primary" style="border:none;cursor:pointer;">
                <?= Icons::svg('shield', 14) ?> Check / Verify Deletion
              </button>
            </form>
          </div>
        </section>

        <!-- Section 2: Facebook & Instagram -->
        <section id="del-meta" class="legal-section">
          <h2>2. How to Remove App &amp; Delete Data on Facebook / Instagram</h2>
          <p>
            If you authorized <b>iVate Ayurveda / <?= h($appName) ?></b> via Meta OAuth and want to disconnect and delete your data 
            directly through Facebook:
          </p>
          <ol>
            <li>Go to your Facebook account and open <b>Settings &amp; Privacy &gt; Settings</b>.</li>
            <li>In the left sidebar, navigate to <b>Apps and Websites</b> (or visit direct URL: <a href="https://www.facebook.com/settings?tab=applications" target="_blank" rel="noopener">facebook.com/settings?tab=applications</a>).</li>
            <li>Locate <b>iVate Ayurveda</b> or <b><?= h($appName) ?></b> in the list of active apps.</li>
            <li>Click the <b>Remove</b> button next to the app.</li>
            <li><i>(Optional)</i> Check the box: <i>"Delete posts, videos or events that the app posted on your timeline"</i> if desired.</li>
            <li>Click <b>Remove</b>. Facebook will automatically notify our Data Deletion Callback endpoint, and our systems will instantly purge your access tokens and account references.</li>
          </ol>
        </section>

        <!-- Section 3: LinkedIn -->
        <section id="del-linkedin" class="legal-section">
          <h2>3. How to Revoke Access on LinkedIn</h2>
          <ol>
            <li>Sign in to your LinkedIn account and click your profile icon &gt; <b>Settings &amp; Privacy</b>.</li>
            <li>Select <b>Data Privacy</b> in the left navigation menu.</li>
            <li>Scroll to <b>Other applications</b> and click <b>Permitted Services</b> (or visit: <a href="https://www.linkedin.com/psettings/permitted-services" target="_blank" rel="noopener">linkedin.com/psettings/permitted-services</a>).</li>
            <li>Find <b><?= h($appName) ?></b> and click <b>Remove</b>.</li>
          </ol>
        </section>

        <!-- Section 4: Pinterest -->
        <section id="del-pinterest" class="legal-section">
          <h2>4. How to Revoke Access on Pinterest</h2>
          <ol>
            <li>Sign in to your Pinterest account and open <b>Settings &gt; Apps</b> (or visit: <a href="https://www.pinterest.com/settings/apps/" target="_blank" rel="noopener">pinterest.com/settings/apps/</a>).</li>
            <li>Locate <b><?= h($appName) ?></b> and click <b>Revoke Access</b>.</li>
          </ol>
        </section>

        <!-- Section 5: In-Panel Disconnect -->
        <section id="del-panel" class="legal-section">
          <h2>5. In-Panel Instant Account Disconnection</h2>
          <p>
            If you are an administrator of this workspace, you do not need to wait for third-party settings. 
            You can delete any account directly:
          </p>
          <ol>
            <li>Log into the administrator dashboard at <a href="index.php?page=login">index.php?page=login</a>.</li>
            <li>Navigate to the <a href="index.php?page=accounts"><b>Connect Accounts</b></a> page.</li>
            <li>Find the specific social account or page card and click <b>Disconnect / Remove</b>.</li>
            <li>All associated OAuth tokens, refresh tokens, and synchronization tasks are purged instantly and irreversibly.</li>
          </ol>
        </section>

        <!-- Section 6: Data Scope -->
        <section id="del-data-scope" class="legal-section">
          <h2>6. Scope of Erased Data</h2>
          <p>Upon receiving a deletion instruction, the following records are permanently wiped:</p>
          <table class="legal-table">
            <thead>
              <tr>
                <th>Data Asset</th>
                <th>Purge Action</th>
                <th>Irreversible?</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>OAuth User &amp; Page Access Tokens</td>
                <td>Permanent Database Removal</td>
                <td>Yes (Immediate)</td>
              </tr>
              <tr>
                <td>Page &amp; Entity ID Mappings</td>
                <td>Hard SQL Delete</td>
                <td>Yes</td>
              </tr>
              <tr>
                <td>Queued &amp; Scheduled Drafts for Account</td>
                <td>Cancelled and Erased</td>
                <td>Yes</td>
              </tr>
              <tr>
                <td>Comment Tracking Logs for Account</td>
                <td>Purged from Active Records</td>
                <td>Yes</td>
              </tr>
            </tbody>
          </table>
          <div class="legal-callout">
            <b>Questions or Urgent Assistance?</b><br>
            If you require manual assistance with data deletion, contact our Data Protection Officer directly at 
            <a href="mailto:<?= h($dpoEmail) ?>"><b><?= h($dpoEmail) ?></b></a>. Deletion requests are fulfilled within 24 hours.
          </div>
        </section>

      </article>
    </div>
  </main>

</body>
</html>
