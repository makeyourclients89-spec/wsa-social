<?php
/**
 * WSA Multi-Channel Publisher - Official Privacy Policy
 * Live URL: https://atopbuilders.com/tools/wsa-social/privacy.php
 * Compliant with Meta Developer Policies, GDPR, and California Consumer Privacy Act (CCPA).
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
  <title>Privacy Policy - <?= htmlspecialchars($appName) ?></title>
  <meta name="description" content="Official Privacy Policy for <?= htmlspecialchars($appName) ?> on <?= htmlspecialchars($websiteUrl) ?>.">
  <style>
    :root {
      --bg: #0f172a;
      --card-bg: #1e293b;
      --border: #334155;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --primary: #3b82f6;
      --primary-hover: #2563eb;
      --accent: #10b981;
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
    .highlight-box {
      background: rgba(16, 185, 129, 0.1);
      border-left: 4px solid var(--accent);
      padding: 16px 20px;
      border-radius: 8px;
      margin: 20px 0;
    }
    .highlight-box p { margin: 0; color: #a7f3d0; font-size: 14px; }
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
      <span class="badge">Official Meta Compliance</span>
      <h1>Privacy Policy</h1>
      <div class="updated">Last Updated: <?= htmlspecialchars($lastUpdated) ?> · Application: <strong><?= htmlspecialchars($appName) ?></strong></div>
    </header>

    <div class="highlight-box">
      <p><strong>Summary:</strong> <?= htmlspecialchars($appName) ?> is an internal social media publishing and workflow management software operated by <?= htmlspecialchars($companyName) ?>. We access Meta (Facebook & Instagram) API credentials solely to publish authorized marketing updates, schedule posts, and reply to customer inquiries on your connected business pages. We never sell, share, or monetize your personal data.</p>
    </div>

    <h2>1. Information We Collect</h2>
    <p>When you authenticate your Facebook or Instagram accounts with <?= htmlspecialchars($appName) ?>, we collect and store only the minimal technical data required to deliver automated publishing services:</p>
    <ul>
      <li><strong>Meta Account Identifiers:</strong> Your Facebook User ID, connected Facebook Page IDs, and Instagram Professional Account IDs.</li>
      <li><strong>Page Access Tokens:</strong> Secure OAuth access tokens granted by you during authorization, utilized strictly to execute publishing and comment operations on your designated Pages.</li>
      <li><strong>Published Campaign Content:</strong> Post captions, target links, media attachments, and scheduling timestamps created within your dashboard.</li>
      <li><strong>Operational Logs:</strong> Timestamps and delivery statuses of dispatched posts for audit and troubleshooting purposes.</li>
    </ul>

    <h2>2. How We Use Your Information</h2>
    <p>We process your data strictly for legitimate operational purposes:</p>
    <ul>
      <li>To schedule and automatically publish posts, photos, and announcements to your connected Facebook Pages and Instagram accounts.</li>
      <li>To retrieve page lists and metrics for your personal agency dashboard.</li>
      <li>To automate comment responses and lead generation redirects (e.g., WhatsApp click-to-chat links) on your authorized posts.</li>
    </ul>

    <h2>3. Data Protection and Security</h2>
    <p>We maintain strict administrative, technical, and physical safeguards:</p>
    <ul>
      <li>All API communications with Meta Graph API are transmitted exclusively over encrypted HTTPS (TLS 1.2+).</li>
      <li>Access tokens and API credentials are stored in secure, private server storage with restricted access controls.</li>
      <li>We do not share, lease, rent, or transfer your tokens or confidential content to any third-party advertisers or external data brokers.</li>
    </ul>

    <h2>4. Third-Party Services (Meta Platform)</h2>
    <p>Our application interacts directly with Meta Platforms, Inc. APIs. Your use of Facebook and Instagram features is governed by the official <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener">Meta Privacy Policy</a> and <a href="https://developers.facebook.com/terms/" target="_blank" rel="noopener">Meta Platform Terms</a>.</p>

    <h2>5. Data Retention and Deletion</h2>
    <p>We retain your connected page details and OAuth tokens only as long as you maintain an active connection in the platform. You may revoke access or request immediate data deletion at any time:</p>
    <ul>
      <li><strong>Instant Self-Service Revocation:</strong> You can disconnect and remove <?= htmlspecialchars($appName) ?> anytime via your <a href="https://www.facebook.com/settings?tab=business_tools" target="_blank" rel="noopener">Facebook Business Integrations Settings</a>.</li>
      <li><strong>Data Deletion Request Callback:</strong> We provide an automated data deletion callback and manual instructions at our <a href="deletion.php">User Data Deletion Page</a>.</li>
    </ul>

    <h2>6. Contact Us</h2>
    <p>If you have any questions about this Privacy Policy or wish to request data erasure, please reach out to our team:</p>
    <ul>
      <li><strong>Organization:</strong> <?= htmlspecialchars($companyName) ?></li>
      <li><strong>Application URL:</strong> <a href="<?= htmlspecialchars($websiteUrl) ?>"><?= htmlspecialchars($websiteUrl) ?></a></li>
      <li><strong>Support Email:</strong> <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a></li>
    </ul>

    <footer>
      <div>&copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>. All rights reserved.</div>
      <div class="nav-links">
        <a href="terms.php">Terms of Service</a>
        <a href="deletion.php">Data Deletion Instructions</a>
      </div>
    </footer>
  </div>
</body>
</html>
