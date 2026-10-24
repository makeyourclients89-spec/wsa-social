<?php
declare(strict_types=1);

$metaCreds = MetaGraph::getCredentials(db());
$metaCallbackUrl = MetaGraph::detectRedirectUri();

$linkedinCreds = LinkedInClient::getCredentials(db());
$linkedinCallbackUrl = LinkedInClient::detectRedirectUri();

$pinterestCreds = PinterestClient::getCredentials(db());
$pinterestCallbackUrl = PinterestClient::detectRedirectUri();

$privacyUrl = app_base_url() . '?page=privacy';
$termsUrl = app_base_url() . '?page=terms';
$dataDeletionUrl = app_base_url() . '?page=data-deletion';
?>
<div class="hero small">
  <div>
    <span class="eyebrow">System Configuration</span>
    <h1>Settings &amp; API Integrations</h1>
    <p>Configure official OAuth 2.0 apps for Meta (Facebook &amp; Instagram), LinkedIn, and Pinterest auto-posting &amp; commenting.</p>
  </div>
</div>

<!-- OAuth App Review & Compliance URLs -->
<div class="card mb-20" style="margin-bottom: 24px;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;font-size:16px;font-weight:700;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:#eff6ff;color:#2563eb;">
          <?= Icons::svg('shield', 16) ?>
        </span>
        OAuth App Review &amp; Compliance URLs (Meta, LinkedIn, Pinterest)
      </h2>
      <p class="text-muted" style="margin:0;font-size:12.5px;">
        These exact public URLs must be entered in your developer portal (<b>App Settings &gt; Basic</b>) to pass verification and enable live mode.
      </p>
    </div>
    <span class="badge-active" style="padding:5px 12px;font-size:11.5px;">
      <?= Icons::svg('check', 13) ?> Public &amp; Active
    </span>
  </div>

  <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px; margin-top: 18px;">
    <div style="background:var(--bg);padding:14px 16px;border-radius:10px;border:1px solid var(--line);">
      <b style="font-size:11px;display:block;margin-bottom:6px;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;">Privacy Policy URL</b>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <code style="font-size:11px;overflow-wrap:anywhere;word-break:break-all;flex:1;background:var(--card-bg);padding:5px 8px;border-radius:6px;border:1px solid var(--line);"><?= h($privacyUrl) ?></code>
        <a href="<?= h($privacyUrl) ?>" target="_blank" class="smallbtn" style="white-space:nowrap;padding:5px 10px;font-size:11.5px;font-weight:700;">Open ↗</a>
      </div>
    </div>

    <div style="background:var(--bg);padding:14px 16px;border-radius:10px;border:1px solid var(--line);">
      <b style="font-size:11px;display:block;margin-bottom:6px;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;">Terms of Service URL</b>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <code style="font-size:11px;overflow-wrap:anywhere;word-break:break-all;flex:1;background:var(--card-bg);padding:5px 8px;border-radius:6px;border:1px solid var(--line);"><?= h($termsUrl) ?></code>
        <a href="<?= h($termsUrl) ?>" target="_blank" class="smallbtn" style="white-space:nowrap;padding:5px 10px;font-size:11.5px;font-weight:700;">Open ↗</a>
      </div>
    </div>

    <div style="background:var(--bg);padding:14px 16px;border-radius:10px;border:1px solid var(--line);">
      <b style="font-size:11px;display:block;margin-bottom:6px;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;">User Data Deletion URL</b>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <code style="font-size:11px;overflow-wrap:anywhere;word-break:break-all;flex:1;background:var(--card-bg);padding:5px 8px;border-radius:6px;border:1px solid var(--line);"><?= h($dataDeletionUrl) ?></code>
        <a href="<?= h($dataDeletionUrl) ?>" target="_blank" class="smallbtn" style="white-space:nowrap;padding:5px 10px;font-size:11.5px;font-weight:700;">Open ↗</a>
      </div>
    </div>
  </div>
</div>

<div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px;">
  <!-- Meta Graph API Integration Settings -->
  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h2 style="display:flex; align-items:center; gap:8px; margin:0; font-size:15px; font-weight:700;">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#eff6ff;color:#1877f2;">
            <?= Icons::platform('facebook', 16) ?>
          </span>
          Meta Graph API
        </h2>
        <span class="badge-meta" style="font-size:10px;">FB &amp; IG</span>
      </div>
      <p class="text-muted" style="margin-top:-4px;margin-bottom:16px;font-size:12px;line-height:1.45;">
        Developer App credentials for live Facebook Pages &amp; Instagram Professional auto-posting &amp; auto-commenting.
      </p>
      <form method="post" action="index.php?page=settings-meta">
        <?= csrf_field() ?>
        <label for="meta_app_id">Meta App ID
          <input id="meta_app_id" name="meta_app_id" value="<?= h($metaCreds['app_id']) ?>" placeholder="e.g. 152849204859302">
        </label>
        <label for="meta_app_secret">Meta App Secret
          <input id="meta_app_secret" type="password" name="meta_app_secret" value="<?= h($metaCreds['app_secret']) ?>" placeholder="Meta App Secret Key">
        </label>
        <label for="meta_redirect_uri">Custom Redirect URI (Optional)
          <input id="meta_redirect_uri" name="meta_redirect_uri" value="<?= h($metaCreds['redirect_uri']) ?>" placeholder="<?= h($metaCallbackUrl) ?>">
        </label>
        <div class="info mt-4" style="font-size:11.5px;margin-top:12px;line-height:1.45;border-radius:8px;">
          <b style="color:var(--ink);">Meta Portal:</b> Valid OAuth Redirect URI:<br>
          <code style="overflow-wrap:anywhere;word-break:break-all;margin-top:4px;display:inline-block;"><?= h($metaCallbackUrl) ?></code>
        </div>
        <button type="submit" style="width:100%;margin-top:16px;justify-content:center;">Save Meta Credentials</button>
      </form>
    </div>
  </div>

  <!-- LinkedIn API Integration Settings -->
  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h2 style="display:flex; align-items:center; gap:8px; margin:0; font-size:15px; font-weight:700;">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#f0f9ff;color:#0a66c2;">
            <?= Icons::platform('linkedin', 16) ?>
          </span>
          LinkedIn API
        </h2>
        <span class="badge-linkedin" style="font-size:10px;">Community</span>
      </div>
      <p class="text-muted" style="margin-top:-4px;margin-bottom:16px;font-size:12px;line-height:1.45;">
        Developer App credentials for member &amp; company page auto-posting and auto-commenting.
      </p>
      <form method="post" action="index.php?page=settings-linkedin">
        <?= csrf_field() ?>
        <label for="li_client_id">LinkedIn Client ID
          <input id="li_client_id" name="linkedin_client_id" value="<?= h($linkedinCreds['client_id']) ?>" placeholder="e.g. 78a9bc12de34">
        </label>
        <label for="li_client_secret">LinkedIn Client Secret
          <input id="li_client_secret" type="password" name="linkedin_client_secret" value="<?= h($linkedinCreds['client_secret']) ?>" placeholder="LinkedIn Client Secret">
        </label>
        <label for="li_redirect_uri">Custom Redirect URI (Optional)
          <input id="li_redirect_uri" name="linkedin_redirect_uri" value="<?= h($linkedinCreds['redirect_uri']) ?>" placeholder="<?= h($linkedinCallbackUrl) ?>">
        </label>
        <div class="info mt-4" style="font-size:11.5px;margin-top:12px;line-height:1.45;border-radius:8px;">
          <b style="color:var(--ink);">LinkedIn Portal:</b> Authorized Redirect URL:<br>
          <code style="overflow-wrap:anywhere;word-break:break-all;margin-top:4px;display:inline-block;"><?= h($linkedinCallbackUrl) ?></code>
        </div>
        <button type="submit" style="width:100%;margin-top:16px;justify-content:center;">Save LinkedIn Credentials</button>
      </form>
    </div>
  </div>

  <!-- Pinterest API Integration Settings -->
  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h2 style="display:flex; align-items:center; gap:8px; margin:0; font-size:15px; font-weight:700;">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#fef2f2;color:#e60023;">
            <?= Icons::platform('pinterest', 16) ?>
          </span>
          Pinterest API v5
        </h2>
        <span class="badge-pinterest" style="font-size:10px;">Pins &amp; Boards</span>
      </div>
      <p class="text-muted" style="margin-top:-4px;margin-bottom:16px;font-size:12px;line-height:1.45;">
        Developer App credentials for multi-board synchronization, pin creation, and comment engagement.
      </p>
      <form method="post" action="index.php?page=settings-pinterest">
        <?= csrf_field() ?>
        <label for="pin_app_id">Pinterest App ID / Client ID
          <input id="pin_app_id" name="pinterest_app_id" value="<?= h($pinterestCreds['app_id']) ?>" placeholder="e.g. 1498271">
        </label>
        <label for="pin_app_secret">Pinterest App Secret
          <input id="pin_app_secret" type="password" name="pinterest_app_secret" value="<?= h($pinterestCreds['app_secret']) ?>" placeholder="Pinterest App Secret Key">
        </label>
        <label for="pin_redirect_uri">Custom Redirect URI (Optional)
          <input id="pin_redirect_uri" name="pinterest_redirect_uri" value="<?= h($pinterestCreds['redirect_uri']) ?>" placeholder="<?= h($pinterestCallbackUrl) ?>">
        </label>
        <div class="info mt-4" style="font-size:11.5px;margin-top:12px;line-height:1.45;border-radius:8px;">
          <b style="color:var(--ink);">Pinterest Portal:</b> Redirect URI in Connect:<br>
          <code style="overflow-wrap:anywhere;word-break:break-all;margin-top:4px;display:inline-block;"><?= h($pinterestCallbackUrl) ?></code>
        </div>
        <button type="submit" style="width:100%;margin-top:16px;justify-content:center;">Save Pinterest Credentials</button>
      </form>
    </div>
  </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-top: 20px;">
  <!-- Admin Password Management -->
  <div class="card">
    <h2 style="font-size:15px; font-weight:700; margin-bottom:12px;">Change Administrator Password</h2>
    <form method="post" action="index.php?page=password-change">
      <?= csrf_field() ?>
      <label for="cur_pass">Current Password
        <input id="cur_pass" type="password" name="current_password" required>
      </label>
      <label for="new_pass">New Password (min 8 characters)
        <input id="new_pass" type="password" name="new_password" minlength="8" required>
      </label>
      <label for="conf_pass">Confirm New Password
        <input id="conf_pass" type="password" name="confirm_password" minlength="8" required>
      </label>
      <button type="submit" style="width:100%;margin-top:4px;justify-content:center;">Update Password</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:15px; font-weight:700; margin-bottom:12px;">Background Queue Worker / Cron</h2>
    <p style="font-size:12px; line-height:1.45;" class="text-muted">
      To execute queued multi-channel publishing automatically every minute:
    </p>
    <pre style="background:var(--navy);color:#f8fafc;padding:12px;border-radius:8px;font-size:11px;overflow-wrap:anywhere;word-break:break-all;white-space:pre-wrap;"><code>* * * * * php c:\xampp\htdocs\wsa-social\cli\runner.php >> /dev/null 2>&1</code></pre>
    <div class="info mt-4" style="font-size:11.5px;margin-top:12px;line-height:1.45;">
      <b style="color:var(--ink);">Windows Local Run:</b> You can run <code>C:\xampp\php\php.exe cli\runner.php</code> anytime in PowerShell/Terminal or trigger it from the Dashboard via <b>Run Queue</b> button.
    </div>
  </div>
</div>
