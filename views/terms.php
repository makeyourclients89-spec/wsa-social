<?php
declare(strict_types=1);

$isLoggedIn = logged_in();
$backUrl = $isLoggedIn ? 'index.php?page=dashboard' : 'index.php?page=login';
$backLabel = $isLoggedIn ? '← Back to Dashboard' : '← Back to Login';
$appName = APP_NAME;
$effectiveDate = 'October 10, 2026';
$supportEmail = 'support@wsa.local';
$legalEmail = 'legal@wsa.local';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Terms of Service for WSA Multi-Channel Publisher and iVate Ayurveda platform integrations.">
  <title>Terms of Service - <?= h($appName) ?></title>
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
        <?= Icons::svg('check', 12) ?> Active Agreement
      </span>
    </div>
    <div class="legal-topbar-right">
      <a href="<?= h($backUrl) ?>" class="legal-btn-link">
        <?= h($backLabel) ?>
      </a>
      <a href="index.php?page=privacy" class="legal-btn-link">Privacy Policy</a>
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
        <span class="legal-chip">Enterprise Multi-Channel Agreement</span>
        <span class="legal-chip">Developer API Compliance</span>
      </div>
      <h1>Terms of Service &amp; Master Subscription Agreement</h1>
      <p>
        These Terms of Service govern your access to and use of <b><?= h($appName) ?></b> 
        (operated by <i>iVate Ayurveda / WSA Digital Enterprise</i>), including all software, APIs, multi-channel 
        scheduling tools, automated commenting modules, and connected account integrations.
      </p>
      <div class="legal-meta">
        <span><b>Effective Date:</b> <?= h($effectiveDate) ?></span>
        <span><b>Version:</b> 3.2.0 (Commercial MNC Standards)</span>
        <span><b>Legal Helpdesk:</b> <a href="mailto:<?= h($legalEmail) ?>"><?= h($legalEmail) ?></a></span>
      </div>
    </section>

    <!-- Content Grid with Sticky Sidebar -->
    <div class="legal-grid">
      <!-- Table of Contents -->
      <aside class="legal-toc-sidebar" aria-label="Document Navigation">
        <h3>Contents</h3>
        <ul>
          <li><a href="#tos-1">1. Acceptance of Terms</a></li>
          <li><a href="#tos-2">2. Description of Service</a></li>
          <li><a href="#tos-3">3. Account &amp; Workspace Security</a></li>
          <li><a href="#tos-4">4. Acceptable Use Policy (AUP)</a></li>
          <li><a href="#tos-5">5. Third-Party Platform Policies</a></li>
          <li><a href="#tos-6">6. API Rate Limits &amp; Disclaimers</a></li>
          <li><a href="#tos-7">7. Intellectual Property &amp; Content</a></li>
          <li><a href="#tos-8">8. Warranty &amp; Liability Limitations</a></li>
          <li><a href="#tos-9">9. Termination &amp; Disconnection</a></li>
          <li><a href="#tos-10">10. Governing Law &amp; Jurisdiction</a></li>
        </ul>
      </aside>

      <!-- Legal Article Document -->
      <article class="legal-article">

        <!-- Section 1 -->
        <section id="tos-1" class="legal-section">
          <h2>1. Acceptance of Terms</h2>
          <p>
            By accessing this platform, logging into an administrator account, connecting any social media account 
            (such as Meta Facebook Pages, Instagram Professional Accounts, LinkedIn Profiles or Company Pages, Pinterest Boards, etc.), 
            or scheduling automated marketing campaigns, you agree to be bound by these Terms of Service. 
            If you do not agree to these terms, do not access or use the software.
          </p>
        </section>

        <!-- Section 2 -->
        <section id="tos-2" class="legal-section">
          <h2>2. Description of Service</h2>
          <p>
            <b><?= h($appName) ?></b> is an enterprise social media publishing, queue orchestration, and audience engagement platform. 
            Key capabilities include:
          </p>
          <ul>
            <li>Centralized multi-workspace campaign drafting and asset scheduling across 20+ supported digital platforms.</li>
            <li>Direct API publishing to authorized accounts via official OAuth 2.0 endpoints.</li>
            <li>Automated customer engagement and comment response generation.</li>
            <li>Multi-channel rate limiting and compliance telemetry to prevent platform quota overages.</li>
          </ul>
        </section>

        <!-- Section 3 -->
        <section id="tos-3" class="legal-section">
          <h2>3. Account &amp; Workspace Security Responsibilities</h2>
          <p>
            Administrators and operators are responsible for maintaining the confidentiality of their login credentials. 
            You agree to:
          </p>
          <ul>
            <li>Maintain strong, unique passwords and update default system passwords immediately upon initial provisioning.</li>
            <li>Promptly notify the platform engineering team of any suspected unauthorized access or compromise of API keys.</li>
            <li>Ensure that authorized operators who have access to workspace accounts have the necessary organizational permissions to publish content on behalf of the represented brands.</li>
          </ul>
        </section>

        <!-- Section 4 -->
        <section id="tos-4" class="legal-section">
          <h2>4. Acceptable Use Policy (AUP) &amp; Community Integrity</h2>
          <div class="legal-callout callout-warn">
            <b>Strict Prohibition of Malicious Automation:</b><br>
            You agree NEVER to use <?= h($appName) ?> to:
            <ul style="margin-top:6px;margin-bottom:0;">
              <li>Transmit unsolicited commercial bulk messaging ("spam"), deceptive affiliate links, phishing campaigns, or fraudulent financial schemes.</li>
              <li>Publish hate speech, violent incitement, defamatory materials, sexually explicit content, or content violating copyright, trademark, or intellectual property rights.</li>
              <li>Execute automated harassment, brigading, or mass synthetic comment farming that disrupts normal social community experiences.</li>
              <li>Reverse engineer or compromise the integrity of third-party platform API gateways.</li>
            </ul>
          </div>
          <p>
            Violation of this Acceptable Use Policy constitutes grounds for immediate, permanent termination of workspace privileges 
            and revocation of all active OAuth tokens.
          </p>
        </section>

        <!-- Section 5 -->
        <section id="tos-5" class="legal-section">
          <h2>5. Third-Party Platform Policies (Meta, LinkedIn, Pinterest)</h2>
          <p>
            Users must comply at all times with the developer terms and community policies of every connected network:
          </p>
          <ul>
            <li><b>Meta Platforms:</b> Adhere to the <a href="https://developers.facebook.com/terms/" target="_blank" rel="noopener">Meta Platform Terms</a>, <a href="https://www.facebook.com/communitystandards/" target="_blank" rel="noopener">Facebook Community Standards</a>, and <a href="https://help.instagram.com/477434105621119" target="_blank" rel="noopener">Instagram Community Guidelines</a>.</li>
            <li><b>LinkedIn:</b> Adhere to the <a href="https://legal.linkedin.com/api-terms-of-use" target="_blank" rel="noopener">LinkedIn API Terms of Use</a> and <a href="https://www.linkedin.com/legal/user-agreement" target="_blank" rel="noopener">User Agreement</a>.</li>
            <li><b>Pinterest:</b> Adhere to the <a href="https://policy.pinterest.com/en/developer-guidelines" target="_blank" rel="noopener">Pinterest Developer Guidelines</a> and <a href="https://policy.pinterest.com/en/community-guidelines" target="_blank" rel="noopener">Community Guidelines</a>.</li>
          </ul>
        </section>

        <!-- Section 6 -->
        <section id="tos-6" class="legal-section">
          <h2>6. API Rate Limits &amp; External Platform Dependency</h2>
          <p>
            Connected networks impose daily and hourly API rate limits (e.g. Meta Graph API call limits, LinkedIn daily update caps, 
            Pinterest pin generation limits).
          </p>
          <div class="legal-callout">
            <b>Quota Disclaimers:</b><br>
            <?= h($appName) ?> enforces built-in safeguards to schedule and throttle requests safely. However, we are not liable for:
            <ol style="margin-top:6px;margin-bottom:0;">
              <li>Third-party platform API service outages, latency, or unscheduled maintenance.</li>
              <li>Platform policy updates, deprecations of specific endpoints, or account restrictions imposed directly by Meta, LinkedIn, or Pinterest.</li>
              <li>Failed post deliveries resulting from expired user authentication tokens or altered page permissions.</li>
            </ol>
          </div>
        </section>

        <!-- Section 7 -->
        <section id="tos-7" class="legal-section">
          <h2>7. Intellectual Property &amp; Content Ownership</h2>
          <p>
            <b>Your Content Remains Yours:</b> You retain complete, unrestricted ownership of all text, images, videos, trademarks, 
            and creative assets you upload or schedule through the platform.
          </p>
          <p>
            <b>Limited Operational License:</b> By creating a campaign and connecting social accounts, you grant 
            <?= h($appName) ?> a limited, non-exclusive, worldwide, royalty-free license solely to format, transmit, 
            and publish that content to your authorized destination endpoints via third-party APIs as requested by you.
          </p>
        </section>

        <!-- Section 8 -->
        <section id="tos-8" class="legal-section">
          <h2>8. Warranty Disclaimers &amp; Limitation of Liability</h2>
          <p>
            THE SERVICE IS PROVIDED ON AN "AS IS" AND "AS AVAILABLE" BASIS WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED, 
            INCLUDING BUT NOT LIMITED TO WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, OR NON-INFRINGEMENT.
          </p>
          <p>
            IN NO EVENT SHALL <?= strtoupper(h($appName)) ?>, IVATE AYURVEDA, OR ITS AFFILIATES BE LIABLE FOR ANY INDIRECT, INCIDENTAL, 
            SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING LOSS OF PROFITS, DATA, USE, GOODWILL, OR OTHER INTANGIBLE LOSSES 
            ARISING OUT OF YOUR USE OF OR INABILITY TO USE THE PLATFORM.
          </p>
        </section>

        <!-- Section 9 -->
        <section id="tos-9" class="legal-section">
          <h2>9. Termination, Disconnection &amp; Data Deletion</h2>
          <p>
            You may discontinue using <?= h($appName) ?> at any time. You can disconnect individual social media accounts 
            from the <b>Accounts</b> menu or invoke our automated <a href="index.php?page=data-deletion"><b>User Data Deletion tool</b></a>. 
            Upon disconnection, all stored OAuth tokens for that entity are immediately wiped from our production database.
          </p>
        </section>

        <!-- Section 10 -->
        <section id="tos-10" class="legal-section">
          <h2>10. Governing Law &amp; Dispute Resolution</h2>
          <p>
            These Terms shall be governed by and construed in accordance with applicable laws, without regard to its conflict 
            of law provisions. Any dispute arising out of or relating to these Terms shall be resolved through good-faith negotiation 
            or submitted to competent courts in our primary operating jurisdiction.
          </p>
          <div class="legal-callout">
            <b>Questions regarding these Terms?</b><br>
            Legal &amp; Regulatory Compliance Team<br>
            <b>Email:</b> <a href="mailto:<?= h($legalEmail) ?>"><?= h($legalEmail) ?></a><br>
            <b>Customer Support:</b> <a href="mailto:<?= h($supportEmail) ?>"><?= h($supportEmail) ?></a>
          </div>
        </section>

      </article>
    </div>
  </main>

</body>
</html>
