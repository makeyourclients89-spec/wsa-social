<?php
/**
 * WSA Multi-Channel Publisher - Terms of Service
 * Live URL: https://atopbuilders.com/tools/wsa-social/terms.php
 * Meta Developer Compliant Terms of Service
 */
declare(strict_types=1);

$lastUpdated = 'October 10, 2026';
$appName = 'WSA Multi-Channel Publisher';
$companyName = 'WSA Digital Agency & Atop Builders';
$contactEmail = 'support@wsadigital.com';
$websiteUrl = 'https://atopbuilders.com/tools/wsa-social/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Terms of Service - <?= htmlspecialchars($appName) ?></title>
  <style>
    :root {
      --bg: #0f172a;
      --card-bg: #1e293b;
      --border: #334155;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --primary: #3b82f6;
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
      margin-bottom: 32px;
    }
    .badge {
      display: inline-block;
      background: rgba(59, 130, 246, 0.15);
      color: #60a5fa;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }
    h1 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
    .updated { font-size: 13px; color: var(--muted); }
    h2 { font-size: 19px; font-weight: 700; color: #93c5fd; margin: 30px 0 12px; }
    p, ul { margin-bottom: 16px; font-size: 14.5px; color: #cbd5e1; }
    ul { padding-left: 24px; }
    li { margin-bottom: 8px; }
    strong { color: #fff; }
    a { color: var(--primary); text-decoration: none; font-weight: 600; }
    a:hover { text-decoration: underline; }
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
      <span class="badge">Legal Terms</span>
      <h1>Terms of Service</h1>
      <div class="updated">Last Updated: <?= htmlspecialchars($lastUpdated) ?> · Application: <strong><?= htmlspecialchars($appName) ?></strong></div>
    </header>

    <h2>1. Acceptance of Terms</h2>
    <p>By connecting your Facebook or Instagram accounts and utilizing <?= htmlspecialchars($appName) ?> ("Service"), operated by <?= htmlspecialchars($companyName) ?>, you agree to comply with and be bound by these Terms of Service.</p>

    <h2>2. Purpose of the Service</h2>
    <p><?= htmlspecialchars($appName) ?> provides automated scheduling, cross-channel social publishing, real-time comment management, and digital marketing workflow management for authorized administrators and clients.</p>

    <h2>3. Compliance with Social Network Policies</h2>
    <p>You agree to use this Service strictly in accordance with all applicable third-party platform terms and community guidelines, including but not limited to:</p>
    <ul>
      <li><a href="https://developers.facebook.com/terms/" target="_blank" rel="noopener">Meta Platform Terms</a></li>
      <li><a href="https://www.facebook.com/communitystandards/" target="_blank" rel="noopener">Meta Community Standards</a></li>
      <li><a href="https://help.instagram.com/477434105621119" target="_blank" rel="noopener">Instagram Community Guidelines</a></li>
    </ul>

    <h2>4. Account Ownership &amp; Authorization</h2>
    <p>You represent and warrant that you hold legitimate administrative rights to all Facebook Pages and Instagram accounts that you link to <?= htmlspecialchars($appName) ?>. You are solely responsible for the content and media dispatched through your accounts.</p>

    <h2>5. Termination of Access</h2>
    <p>You may disconnect your accounts at any time via the panel or directly from Facebook's Business Integrations settings. We reserve the right to suspend or terminate access to any workspace violating applicable laws or platform terms.</p>

    <h2>6. Limitation of Liability</h2>
    <p>The Service is provided on an "as is" and "as available" basis. <?= htmlspecialchars($companyName) ?> shall not be liable for any indirect, incidental, or consequential damages resulting from third-party API rate limits, temporary platform downtime, or account actions taken by Meta Platforms, Inc.</p>

    <h2>7. Contact Information</h2>
    <p>Questions regarding these Terms should be directed to <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.</p>

    <footer>
      <div>&copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>. All rights reserved.</div>
      <div class="nav-links">
        <a href="privacy.php">Privacy Policy</a>
        <a href="deletion.php">Data Deletion Instructions</a>
      </div>
    </footer>
  </div>
</body>
</html>
