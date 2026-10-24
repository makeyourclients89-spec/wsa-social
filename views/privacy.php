<?php
declare(strict_types=1);

$isLoggedIn = logged_in();
$backUrl = $isLoggedIn ? 'index.php?page=dashboard' : 'index.php?page=login';
$backLabel = $isLoggedIn ? '← Back to Dashboard' : '← Back to Login';
$appName = APP_NAME;
$effectiveDate = 'October 10, 2026';
$dpoEmail = 'privacy@wsa.local';
$contactEmail = 'support@wsa.local';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Official Privacy Policy for WSA Multi-Channel Publisher and iVate Ayurveda platform integrations.">
  <title>Privacy Policy - <?= h($appName) ?></title>
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
        <?= Icons::svg('shield', 12) ?> Compliant &amp; Active
      </span>
    </div>
    <div class="legal-topbar-right">
      <a href="<?= h($backUrl) ?>" class="legal-btn-link">
        <?= h($backLabel) ?>
      </a>
      <a href="index.php?page=terms" class="legal-btn-link">Terms of Service</a>
      <a href="index.php?page=data-deletion" class="legal-btn-link">Data Deletion</a>
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
        <span class="legal-chip">MNC Enterprise Legal Standard</span>
        <span class="legal-chip">Meta Platform Policy 2026</span>
        <span class="legal-chip">GDPR / DPDP / CCPA Verified</span>
      </div>
      <h1>Privacy Policy &amp; Data Protection Protocol</h1>
      <p>
        Transparency and security are foundational to our platform. This policy outlines how <b><?= h($appName) ?></b> 
        (operating under <i>iVate Ayurveda / WSA Digital Enterprise</i>) accesses, processes, encrypts, and retains 
        user data, OAuth authentication tokens, and social media account credentials.
      </p>
      <div class="legal-meta">
        <span><b>Effective Date:</b> <?= h($effectiveDate) ?></span>
        <span><b>Version:</b> 3.4.0 (Enterprise Multi-Channel)</span>
        <span><b>DPO Contact:</b> <a href="mailto:<?= h($dpoEmail) ?>"><?= h($dpoEmail) ?></a></span>
      </div>
    </section>

    <!-- Content Grid with Sticky Sidebar -->
    <div class="legal-grid">
      <!-- Table of Contents -->
      <aside class="legal-toc-sidebar" aria-label="Document Navigation">
        <h3>Contents</h3>
        <ul>
          <li><a href="#section-1">1. Scope &amp; Identity</a></li>
          <li><a href="#section-2">2. Data We Collect</a></li>
          <li><a href="#section-3">3. OAuth Permissions &amp; Use</a></li>
          <li><a href="#section-4">4. Meta &amp; Instagram Protocol</a></li>
          <li><a href="#section-5">5. LinkedIn &amp; Pinterest Protocol</a></li>
          <li><a href="#section-6">6. No Data Resale Guarantee</a></li>
          <li><a href="#section-7">7. Encryption &amp; Security</a></li>
          <li><a href="#section-8">8. Data Retention &amp; Erasure</a></li>
          <li><a href="#section-9">9. International Privacy Rights</a></li>
          <li><a href="#section-10">10. Contact &amp; Grievance Redressal</a></li>
        </ul>
      </aside>

      <!-- Legal Article Document -->
      <article class="legal-article">

        <!-- Section 1 -->
        <section id="section-1" class="legal-section">
          <h2>1. Scope &amp; Organizational Identity</h2>
          <p>
            This Privacy Policy applies to the <b><?= h($appName) ?></b> web application, associated background job workers, 
            and official API integrations provided by <i>iVate Ayurveda</i> and <i>WSA Digital Systems</i> ("we", "us", or "our").
          </p>
          <p>
            Our software enables enterprise multi-channel publishing, campaign orchestration, scheduled social media posting, 
            and automated customer comment management across connected networks including Meta (Facebook &amp; Instagram), 
            LinkedIn, Pinterest, Twitter/X, YouTube, TikTok, and blogging platforms.
          </p>
        </section>

        <!-- Section 2 -->
        <section id="section-2" class="legal-section">
          <h2>2. Information We Collect and Process</h2>
          <p>We process only the minimum necessary data required to deliver scheduling and publishing features:</p>
          <ul>
            <li>
              <b>OAuth Authentication Credentials:</b> Temporary authorization codes, secure long-lived access tokens, 
              refresh tokens, and token expiration timestamps issued directly by social networks upon user authorization.
            </li>
            <li>
              <b>Social Account &amp; Page Metadata:</b> Public entity identifiers (Facebook Page ID, Instagram Business Account ID, 
              LinkedIn Profile URN, LinkedIn Organization URN, Pinterest Board ID), entity names, and public avatar URLs.
            </li>
            <li>
              <b>Publishing Content &amp; Media:</b> Marketing captions, post titles, destination links, campaign media file paths, 
              scheduled publication dates/times, and auto-reply comment templates configured by workspace operators.
            </li>
            <li>
              <b>Technical Telemetry &amp; Audit Logs:</b> Client IP addresses (retained temporarily in rolling 15-minute windows 
              strictly for brute-force rate limiting), user agent signatures, and publishing success/error codes from third-party APIs.
            </li>
          </ul>
        </section>

        <!-- Section 3 -->
        <section id="section-3" class="legal-section">
          <h2>3. OAuth 2.0 Integration &amp; Purpose of Processing</h2>
          <p>
            We do not request or store your personal passwords for any third-party social networks. All integrations utilize 
            industry-standard OAuth 2.0 authentication protocols.
          </p>
          <div class="legal-callout">
            <b>Purpose Limitation:</b> Data retrieved via OAuth APIs is strictly used to:
            <ol style="margin-top:6px;margin-bottom:0;">
              <li>Publish approved campaign posts to the exact destinations selected by the operator.</li>
              <li>Read incoming comments on published posts to provide auto-comment responses and engagement tracking.</li>
              <li>Display delivery status reports and job audit logs within your authorized workspace.</li>
            </ol>
          </div>
        </section>

        <!-- Section 4 -->
        <section id="section-4" class="legal-section">
          <h2>4. Meta Platforms (Facebook &amp; Instagram) Compliance</h2>
          <p>
            Pursuant to the <b>Meta Platform Terms</b> and <b>Developer Data Use Policy</b>, our integration with 
            Meta Graph API v19.0+ adheres to strict privacy and scope boundaries:
          </p>
          
          <table class="legal-table">
            <thead>
              <tr>
                <th>Scope / Permission</th>
                <th>Service Justification</th>
                <th>Data Handling</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>pages_show_list</code></td>
                <td>Enables operators to select which Facebook Pages they manage in the workspace.</td>
                <td>Reads Page names and IDs; no personal profile data is collected.</td>
              </tr>
              <tr>
                <td><code>pages_manage_posts</code></td>
                <td>Allows our automated queue to publish scheduled posts to authorized Facebook Pages.</td>
                <td>Sends post caption, image, or link to the Graph API.</td>
              </tr>
              <tr>
                <td><code>pages_read_engagement</code></td>
                <td>Monitors comments and reactions on Page posts for reporting and auto-engagement.</td>
                <td>Reads comment text and comment ID to trigger automated replies.</td>
              </tr>
              <tr>
                <td><code>pages_manage_engagement</code></td>
                <td>Publishes replies to comments on authorized Facebook Page posts.</td>
                <td>Sends reply text on behalf of the Page identity.</td>
              </tr>
              <tr>
                <td><code>instagram_basic</code></td>
                <td>Retrieves connected Instagram Professional Account ID, username, and profile picture.</td>
                <td>Used strictly for account identification and mapping.</td>
              </tr>
              <tr>
                <td><code>instagram_content_publish</code></td>
                <td>Publishes single photos, carousels, and captions to Instagram Business accounts.</td>
                <td>Media URLs are transmitted to Meta's container API.</td>
              </tr>
              <tr>
                <td><code>instagram_manage_comments</code></td>
                <td>Retrieves comments on Instagram media and submits authorized replies.</td>
                <td>Processed via webhook or scheduled background poller.</td>
              </tr>
            </tbody>
          </table>

          <div class="legal-callout callout-meta">
            <b>Meta User Data Deletion Compliance:</b> In accordance with Meta requirements, any user can revoke access 
            or request complete deletion of all associated data at any time. For step-by-step instructions and automated callback status, 
            visit our dedicated <a href="index.php?page=data-deletion"><b>User Data Deletion Page</b></a>.
          </div>
        </section>

        <!-- Section 5 -->
        <section id="section-5" class="legal-section">
          <h2>5. LinkedIn &amp; Pinterest API Specifications</h2>
          <h3>LinkedIn API Integration</h3>
          <p>
            Our LinkedIn integration utilizes scopes <code>openid</code>, <code>profile</code>, <code>w_member_social</code>, 
            and <code>w_organization_social</code> solely to publish member updates and Company Page organizational posts. 
            We do not scrape member networks, harvest connections, or retain personal profile records beyond what is necessary 
            for post authorship attribution.
          </p>

          <h3>Pinterest API v5 Integration</h3>
          <p>
            Our Pinterest integration uses scopes <code>boards:read</code>, <code>pins:read</code>, and <code>pins:write</code> 
            to synchronize board destinations and publish organic image pins. We respect all Pinterest rate limits and community standards.
          </p>
        </section>

        <!-- Section 6 -->
        <section id="section-6" class="legal-section">
          <h2>6. Absolute No-Data-Resale Guarantee</h2>
          <div class="legal-callout callout-warn">
            <b>Zero Data Monetization Guarantee:</b><br>
            We <b>DO NOT</b> sell, rent, license, trade, lease, or monetize user data, access tokens, social media content, 
            or contact information to third-party data brokers, advertising aggregators, or generative AI model training crawlers. 
            Your connected assets remain 100% your property.
          </div>
        </section>

        <!-- Section 7 -->
        <section id="section-7" class="legal-section">
          <h2>7. Data Security &amp; Encryption Architecture</h2>
          <p>We deploy defense-in-depth technical and organizational controls to protect all data:</p>
          <ul>
            <li><b>Encryption in Transit:</b> All communications with third-party APIs and end-user browsers are enforced via TLS 1.3 / HTTPS encryption.</li>
            <li><b>Database Access Controls:</b> Relational database storage is hardened with SQLite Write-Ahead Logging (WAL), restricted filesystem permissions, and Apache <code>.htaccess</code> download blockers preventing direct file access.</li>
            <li><b>Session Hardening:</b> Session cookies utilize <code>HttpOnly</code>, <code>SameSite=Lax</code>, and <code>Secure</code> flags with automatic session regeneration upon login to eliminate session fixation (CWE-384).</li>
            <li><b>CSRF Mitigation:</b> All state-changing POST requests require cryptographically secure per-session anti-CSRF tokens.</li>
            <li><b>Rate Limiting:</b> Authentication endpoints enforce rolling rate limiting to prevent brute-force attacks.</li>
          </ul>
        </section>

        <!-- Section 8 -->
        <section id="section-8" class="legal-section">
          <h2>8. Data Retention &amp; Automatic Erasure</h2>
          <p>
            We adhere to strict data minimization principles. Data retention follows these specific thresholds:
          </p>
          <table class="legal-table">
            <thead>
              <tr>
                <th>Data Type</th>
                <th>Retention Period</th>
                <th>Disposal Method</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>OAuth Access &amp; Refresh Tokens</td>
                <td>Active during connection; immediately wiped upon disconnection or revocation.</td>
                <td>Permanent database delete (SQL <code>DELETE</code>).</td>
              </tr>
              <tr>
                <td>Campaign Drafts &amp; Job Logs</td>
                <td>Retained while workspace is active; purgeable by administrator at any time.</td>
                <td>Hard delete from relational database.</td>
              </tr>
              <tr>
                <td>Temporary Media Uploads</td>
                <td>Kept during campaign queue processing; purgeable via clean-up scripts.</td>
                <td>Filesystem unlink from storage directory.</td>
              </tr>
              <tr>
                <td>Login Attempt Logs</td>
                <td>15 minutes rolling window.</td>
                <td>Automated garbage collection.</td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- Section 9 -->
        <section id="section-9" class="legal-section">
          <h2>9. International Privacy Rights (GDPR, DPDP, CCPA)</h2>
          <p>
            Depending on your jurisdiction, you possess statutory privacy rights including:
          </p>
          <ul>
            <li><b>Right of Access:</b> You have the right to request a copy of the data we hold regarding your workspace.</li>
            <li><b>Right to Rectification:</b> You may correct inaccurate or incomplete configuration details at any time via Settings.</li>
            <li><b>Right to Erasure ("Right to be Forgotten"):</b> You may request immediate purging of all stored records and OAuth credentials.</li>
            <li><b>Right to Withdraw Consent:</b> You can disconnect any OAuth account instantly from the Accounts dashboard or via your provider's security portal.</li>
          </ul>
        </section>

        <!-- Section 10 -->
        <section id="section-10" class="legal-section">
          <h2>10. Contact Information &amp; Grievance Redressal</h2>
          <p>
            If you have questions, feedback, or concerns regarding this Privacy Policy or wish to exercise your data subject rights, 
            please contact our Data Protection Officer (DPO):
          </p>
          <div class="legal-callout">
            <b>Data Protection Officer (DPO):</b><br>
            WSA Digital Network &amp; iVate Ayurveda Compliance Bureau<br>
            <b>Email:</b> <a href="mailto:<?= h($dpoEmail) ?>"><?= h($dpoEmail) ?></a><br>
            <b>Support Helpdesk:</b> <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a><br>
            <b>Website:</b> <a href="<?= h(app_base_url()) ?>"><?= h(app_base_url()) ?></a>
          </div>
        </section>

      </article>
    </div>
  </main>

</body>
</html>
