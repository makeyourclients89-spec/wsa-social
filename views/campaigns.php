<?php
declare(strict_types=1);

/**
 * @var array $clients
 * @var array $campaigns
 * @var array $availableAccounts
 * @var int $activeClientId
 */
$wsaAccounts = [];
$otherAccounts = [];

foreach ($availableAccounts ?? [] as $acc) {
    if (stripos($acc['label'], 'wsa') !== false) {
        $wsaAccounts[] = $acc;
    } else {
        $otherAccounts[] = $acc;
    }
}
?>
<div class="hero small">
  <div>
    <h1 style="letter-spacing:-0.02em; margin:0 0 4px; font-size:22px; font-weight:800; display:flex; align-items:center; gap:8px;">
      <?= Icons::svg('sparkles', 22, 'text-blue') ?>
      <span>Create &amp; Schedule Post</span>
    </h1>
    <p style="font-size:13px; color:var(--muted); margin:0;">
      Facebook, Instagram aur LinkedIn par posts compose karein, images add karein aur schedule karein.
    </p>
  </div>
</div>

<?php
$hasMetaPermIssue = false;
foreach ($campaigns ?? [] as $cmp) {
    if (!empty($cmp['last_job_error']) && (stripos($cmp['last_job_error'], 'pages_manage_posts') !== false || stripos($cmp['last_job_error'], '(#200)') !== false)) {
        $hasMetaPermIssue = true;
        break;
    }
}
?>
<?php if ($hasMetaPermIssue): ?>
  <div style="background:#fffbeb; border:1px solid #fde68a; border-left:5px solid #f59e0b; border-radius:12px; padding:16px 18px; margin-bottom:20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
      <div>
        <b style="color:#92400e; font-size:14px;">⚠️ Facebook Auto-Post Token Setup (App ID: 1090257013843594):</b>
        <p style="color:#78350f; font-size:12px; margin:4px 0 0;">
          <b>Option A:</b> Jis Facebook ID se Meta App banaya hai (Admin ID), browser me wahi ID login rakh kar <b>1-Click Reconnect</b> dabayein.<br>
          <b>Option B (Kisi Bhi ID / Bypass "App Not Active"):</b> Niche <b>Direct Token Sync</b> me token paste karein — system aapki App ID &amp; Secret se permanent token bana kar post turant LIVE kar dega!
        </p>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="index.php?page=meta-connect&client_id=<?= (int)($activeClientId ?: 2) ?>&scope_mode=full" class="btn" style="background:#1877f2; color:#fff; text-decoration:none; font-weight:700; padding:8px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; font-size:12.5px;">
          <?= Icons::platform('facebook', 15) ?>
          <span>1-Click Reconnect (Admin ID)</span>
        </a>
        <a href="https://developers.facebook.com/apps/1090257013843594/roles/roles/" target="_blank" rel="noopener" class="btn" style="background:#475569; color:#fff; text-decoration:none; font-weight:700; padding:8px 12px; border-radius:8px; font-size:12px;">
          ➕ Add Another FB ID in App Roles ↗
        </a>
      </div>
    </div>

    <!-- Direct Token Auto-Upgrader (Uses App ID + Secret stored in DB) -->
    <form method="post" action="index.php?page=meta-direct-sync" style="margin-top:12px; padding-top:12px; border-top:1px dashed #fcd34d; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="client_id" value="<?= (int)($activeClientId ?: 2) ?>">
      <span style="font-size:12px; font-weight:700; color:#92400e;">🔑 Direct Token Paste (<a href="https://developers.facebook.com/tools/explorer/?method=GET&path=me%2Faccounts&version=v19.0" target="_blank" rel="noopener" style="color:#1877f2; text-decoration:underline;">Get from Graph Explorer ↗</a>):</span>
      <input type="text" name="direct_meta_token" required placeholder="Paste EAA... Access Token here (Auto-upgrades with App ID & Secret)" style="flex:1; min-width:240px; padding:7px 12px; font-size:12px; border:1px solid #f59e0b; border-radius:7px; background:#fff; color:#1e293b;">
      <button type="submit" class="btn" style="background:#16a34a; color:#fff; font-weight:700; padding:7px 14px; border-radius:7px; font-size:12px;">
        ⚡ Sync Token &amp; Auto-Publish Pending Posts
      </button>
    </form>
  </div>
<?php endif; ?>

<div class="composer-grid-layout">
  <!-- LEFT: Streamlined & Clean Post Composer -->
  <form class="composer-card" method="post" action="index.php?page=campaign-add" enctype="multipart/form-data" id="composer-form">
    <?= csrf_field() ?>
    <input type="hidden" name="client_id" value="<?= (int)($activeClientId ?: ($clients[0]['id'] ?? 2)) ?>">

    <!-- 1. CHANNEL SELECTION (MNC LEVEL SAAS DESIGN) -->
    <div style="margin-bottom:22px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
        <label class="composer-label" style="margin:0;">1. Post Kahan Dalni Hai (Target Channels):</label>
        <button type="button" class="btn-more-channels" onclick="toggleExtraChannelsDrawer()" id="btn-toggle-extra" style="margin:0;">
          <?= Icons::svg('plus', 13) ?>
          <span>+ Other Brand Pages (<?= count($otherAccounts) ?>)</span>
        </button>
      </div>
      
      <div class="clean-channels-container">
        <!-- Official WSA Primary Channels -->
        <?php foreach ($wsaAccounts as $wAcc): ?>
          <?php $isFb = ($wAcc['platform'] === 'facebook'); ?>
          <label class="channel-pill-toggle <?= $isFb ? 'is-active' : '' ?>" title="<?= h($wAcc['label']) ?>">
            <input type="checkbox" name="account_ids[]" value="<?= (int)$wAcc['id'] ?>" <?= $isFb ? 'checked' : '' ?> onchange="this.closest('.channel-pill-toggle').classList.toggle('is-active', this.checked)">
            <span class="channel-icon-wrap channel-<?= h($wAcc['platform']) ?>" style="width:24px; height:24px; border-radius:6px; flex-shrink:0;">
              <?= Icons::platform($wAcc['platform'], 13) ?>
            </span>
            <div style="display:flex; flex-direction:column; line-height:1.2;">
              <span style="font-size:12.5px; font-weight:700;"><?= h($wAcc['label']) ?></span>
              <small style="font-size:10px; color:var(--muted);"><?= ucfirst(h($wAcc['platform'])) ?> Official</small>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Collapsible Drawer for other brand pages -->
      <div id="extra-channels-drawer" style="display:none; background:var(--bg); border:1.5px solid var(--line); border-radius:12px; padding:16px; margin-top:10px; box-shadow:0 4px 14px rgba(0,0,0,0.03);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid var(--line);">
          <div style="display:flex; align-items:center; gap:6px;">
            <span style="font-size:12px; font-weight:800; color:var(--ink); text-transform:uppercase; letter-spacing:0.04em;">Additional Brand Pages</span>
            <span class="pill" style="font-size:10px; padding:2px 6px;"><?= count($otherAccounts) ?> Available</span>
          </div>
          <div style="display:flex; gap:6px;">
            <button type="button" class="micro-btn" onclick="toggleExtraBoxes(true)">Select All</button>
            <button type="button" class="micro-btn" onclick="toggleExtraBoxes(false)">Clear</button>
          </div>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:8px; max-height:220px; overflow-y:auto; padding:2px;" class="drawer-scroll">
          <?php foreach ($otherAccounts as $oAcc): ?>
            <label class="extra-channel-chip">
              <input type="checkbox" name="account_ids[]" value="<?= (int)$oAcc['id'] ?>" class="extra-acc-cb" onchange="this.closest('.extra-channel-chip').classList.toggle('is-selected', this.checked)">
              <span class="channel-icon-wrap channel-<?= h($oAcc['platform']) ?>" style="width:22px; height:22px; border-radius:5px; flex-shrink:0;">
                <?= Icons::platform($oAcc['platform'], 12) ?>
              </span>
              <span class="chip-label-text" title="<?= h($oAcc['label']) ?>"><?= h($oAcc['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 2. POST CONTENT -->
    <div class="composer-input-group">
      <label class="composer-label" for="cmp_msg">2. Post Caption / Message:</label>
      <textarea id="cmp_msg" name="message" class="composer-textarea" rows="4" required placeholder="Write your post caption, digital marketing highlights, or business announcement here..."></textarea>
      
      <!-- Character Count Meters -->
      <div class="char-counter-container">
        <span id="char-total-badge" style="font-weight:700;">0 characters</span>
        <div class="platform-meters">
          <span class="meter-badge"><?= Icons::platform('facebook', 12) ?> <b>FB:</b> 63k</span>
          <span class="meter-badge"><?= Icons::platform('instagram', 12) ?> <b>IG:</b> <span id="limit-ig">2200</span></span>
          <span class="meter-badge"><?= Icons::platform('x', 12) ?> <b>X:</b> <span id="limit-x">280</span></span>
        </div>
      </div>
    </div>

    <!-- 3. PHOTO / MEDIA UPLOAD -->
    <div class="composer-input-group">
      <label class="composer-label">3. Photo / Creative Image (Optional):</label>
      
      <div class="media-upload-dropzone" id="media-dropzone" onclick="document.getElementById('cmp_media').click()" style="padding:14px;">
        <input type="file" id="cmp_media" name="media" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="handleMediaSelect(this)">
        <div class="dropzone-content" id="dropzone-prompt" style="flex-direction:row; justify-content:center; gap:10px;">
          <?= Icons::svg('upload-cloud', 22, 'text-blue') ?>
          <span class="dropzone-title" style="font-size:13px;">Click to select photo or drag image here</span>
        </div>
      </div>
      
      <!-- Uploaded Media Preview Box -->
      <div class="media-preview-box" id="media-preview-box">
        <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
          <img id="media-preview-img" src="" alt="Media preview" class="media-preview-thumb">
          <div style="overflow:hidden;">
            <b id="media-preview-name" style="font-size:12px; display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">image.png</b>
            <span id="media-preview-size" style="font-size:11px; color:var(--muted);">1.2 MB</span>
          </div>
        </div>
        <button type="button" class="micro-btn danger" onclick="removeMediaAttachment()">Remove</button>
      </div>
    </div>

    <!-- 4. WHATSAPP CTA BUTTON SECTION -->
    <div class="whatsapp-cta-box">
      <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer; margin:0;">
        <span style="display:flex; align-items:center; gap:8px; font-weight:700; font-size:13px; color:#15803d;">
          <input type="checkbox" id="enable_whatsapp" name="enable_whatsapp" value="1" onchange="toggleWhatsAppInput(this.checked)" style="width:16px; height:16px; accent-color:#16a34a;">
          <?= Icons::platform('whatsapp', 18) ?>
          <span>WhatsApp "Send Message" Button Lagayein</span>
        </span>
        <span class="pill" style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700;">Direct Leads</span>
      </label>

      <div id="whatsapp_fields" style="display:none; margin-top:10px; padding-top:10px; border-top:1px dashed #86efac;">
        <div class="twocol" style="gap:10px;">
          <div>
            <label for="wa_num" style="font-size:11.5px; margin-bottom:3px; font-weight:700;">WhatsApp Number (Country Code ke sath)</label>
            <input id="wa_num" name="whatsapp_number" placeholder="e.g. 919876543210" oninput="updateWhatsAppPreview(this.value)">
          </div>
          <div>
            <label for="wa_txt" style="font-size:11.5px; margin-bottom:3px; font-weight:700;">Pre-filled Chat Message (Optional)</label>
            <input id="wa_txt" name="whatsapp_msg" placeholder="e.g. Hello WSA, I need digital marketing info">
          </div>
        </div>
      </div>
    </div>

    <!-- Optional Web Link (when WhatsApp is not used) -->
    <div class="composer-input-group" id="web_link_group">
      <label class="composer-label" for="cmp_url" style="font-size:12px;">Website Link (Optional):</label>
      <input id="cmp_url" name="link_url" placeholder="https://wsadigital.com" oninput="updateLinkPreview(this.value)">
    </div>

    <!-- Hidden Campaign Subject fallback -->
    <input type="hidden" name="name" value="WSA Post - <?= date('M d, H:i') ?>">

    <!-- 5. TIMING & AUTO-BOOST POD (COMPACT & SIMPLE) -->
    <div style="background:var(--bg); border:1px solid var(--line); border-radius:12px; padding:12px 14px; margin-bottom:18px;">
      <!-- Timing Toggle -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <b style="font-size:12.5px; color:var(--ink);">Publishing Mode:</b>
        <div style="display:flex; gap:14px;">
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700; cursor:pointer; margin:0;">
            <input type="radio" name="publish_mode" value="instant" checked onchange="document.getElementById('sched_drawer').style.display='none';">
            <span style="color:#2563eb;">⚡ Turant Live Post</span>
          </label>
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700; cursor:pointer; margin:0;">
            <input type="radio" name="publish_mode" value="scheduled" onchange="document.getElementById('sched_drawer').style.display='block';">
            <span>📅 Schedule</span>
          </label>
        </div>
      </div>

      <!-- Schedule Drawer -->
      <div id="sched_drawer" style="display:none; padding-top:8px; border-top:1px dashed var(--line); margin-bottom:8px;">
        <input type="datetime-local" name="scheduled_for" value="<?= date('Y-m-d\TH:i', time() + 3600) ?>">
      </div>

      <!-- Auto-Boost Single-Line Toggle -->
      <div style="padding-top:10px; border-top:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin:0; font-size:12.5px; font-weight:700;">
          <input type="checkbox" name="auto_boost" value="1" checked style="width:16px; height:16px; accent-color:#2563eb;">
          <span style="color:#1d4ed8;">🚀 Cross-Account Auto-Boost Pod</span>
        </label>
        <small style="color:var(--muted); font-size:11px;">15 pages will drop likes &amp; comments</small>
      </div>
    </div>

    <!-- 6. SUBMIT BUTTON -->
    <button type="submit" class="btn-dispatch-primary" id="btn-submit-post">
      <?= Icons::svg('send', 18) ?>
      <span>🚀 Publish Post Now</span>
    </button>
  </form>

  <!-- RIGHT: Real-Time Live Device Mockup (Facebook & Instagram) -->
  <div class="preview-pane-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <?= Icons::svg('eye', 18, 'text-blue') ?>
        <h3 style="margin:0; font-size:14px; font-weight:800;">Real-Time Device Preview</h3>
      </div>
      <span class="pill" style="font-size:11px; background:#f0fdf4; color:#166534; font-weight:700;">Live Feed</span>
    </div>

    <!-- Platform Mockup Selector Tabs -->
    <div class="preview-tabs">
      <button type="button" class="preview-tab-btn active" id="tab-fb-btn" onclick="switchPreviewDevice('facebook')">
        <?= Icons::platform('facebook', 15) ?>
        <span>Facebook Page Feed</span>
      </button>
      <button type="button" class="preview-tab-btn" id="tab-ig-btn" onclick="switchPreviewDevice('instagram')">
        <?= Icons::platform('instagram', 15) ?>
        <span>Instagram Feed</span>
      </button>
    </div>

    <!-- DEVICE FRAME -->
    <div class="device-mockup-frame">
      <!-- 1. FACEBOOK POST PREVIEW -->
      <div class="fb-post-mockup" id="preview-fb">
        <div class="fb-post-header">
          <div class="fb-user-wrap">
            <div class="fb-avatar">W</div>
            <div class="fb-meta-info">
              <h4>
                <span>WSA Digital Agency</span>
                <span class="fb-badge-verified">✓</span>
              </h4>
              <span class="fb-timestamp">
                <span>Just now</span> · <?= Icons::svg('globe', 11) ?>
              </span>
            </div>
          </div>
          <span style="color:#65676b;"><?= Icons::svg('dots-h', 16) ?></span>
        </div>

        <div class="fb-post-content" id="mockup-fb-text">Welcome to WSA Digital Agency! Type your post caption on the left to see live real-time preview...</div>

        <!-- Attached Media Preview -->
        <div class="fb-media-container" id="mockup-fb-media-wrap">
          <img id="mockup-fb-media" src="" alt="Facebook media preview" class="fb-media-img">
        </div>

        <!-- WhatsApp CTA Card in Mockup -->
        <div class="fb-whatsapp-card" id="mockup-wa-card" style="display:none;">
          <div style="display:flex; align-items:center; gap:10px;">
            <div style="background:#25D366; color:#fff; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
              <?= Icons::platform('whatsapp', 18) ?>
            </div>
            <div>
              <div style="font-size:11px; color:#166534; font-weight:800; text-transform:uppercase;">WHATSAPP CHAT</div>
              <div style="font-size:13px; font-weight:700; color:#064e3b;" id="mockup-wa-label">Chat with WSA Digital Agency</div>
            </div>
          </div>
          <div class="fb-whatsapp-btn">
            <?= Icons::platform('whatsapp', 14) ?>
            <span>Send Message</span>
          </div>
        </div>

        <!-- Standard Link Card Preview -->
        <a href="#" target="_blank" rel="noopener" class="fb-link-card-preview" id="mockup-fb-link-card">
          <span class="fb-link-domain" id="mockup-fb-link-domain">WSADIGITAL.COM</span>
          <div class="fb-link-title" id="mockup-fb-link-title">Official Agency Portal &amp; Client Solutions</div>
        </a>

        <!-- Engagement Bar Stats -->
        <div class="fb-engagement-stats">
          <span style="display:flex; align-items:center; gap:4px;">
            <span style="background:#1877f2; color:#fff; border-radius:50%; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; font-size:10px;">👍</span>
            <span>48</span>
          </span>
          <span>15 comments · 4 shares</span>
        </div>

        <!-- Action Buttons -->
        <div class="fb-actions-bar">
          <div class="fb-action-btn">
            <?= Icons::svg('like', 16) ?>
            <span>Like</span>
          </div>
          <div class="fb-action-btn">
            <?= Icons::svg('message-square', 16) ?>
            <span>Comment</span>
          </div>
          <div class="fb-action-btn">
            <?= Icons::svg('share', 16) ?>
            <span>Share</span>
          </div>
        </div>
      </div>

      <!-- 2. INSTAGRAM POST PREVIEW -->
      <div class="ig-post-mockup" id="preview-ig">
        <div class="ig-post-header">
          <div class="ig-user-wrap">
            <div class="ig-avatar-ring">
              <div class="ig-avatar-inner">W</div>
            </div>
            <span class="ig-username">online.marketing.by.wsadigital</span>
          </div>
          <span style="color:var(--muted);"><?= Icons::svg('dots-h', 16) ?></span>
        </div>

        <div class="ig-media-square" id="mockup-ig-media-wrap">
          <img id="mockup-ig-media" src="" alt="Instagram image" class="ig-media-img" style="display:none;">
          <div id="mockup-ig-placeholder" style="text-align:center; padding:20px; color:var(--muted);">
            <?= Icons::svg('image', 36) ?>
            <span style="display:block; font-size:12px; margin-top:8px;">Attach a photo to preview Instagram post</span>
          </div>
        </div>

        <div class="ig-actions-row">
          <div class="ig-left-actions">
            <?= Icons::svg('like', 20) ?>
            <?= Icons::svg('message-square', 20) ?>
            <?= Icons::svg('send', 20) ?>
          </div>
          <div>
            <?= Icons::svg('bookmark', 20) ?>
          </div>
        </div>

        <div class="ig-likes-count">Liked by indoasiaqualityservices and 42 others</div>

        <div class="ig-caption-wrap">
          <b>online.marketing.by.wsadigital</b>
          <span class="ig-caption-text" id="mockup-ig-text">Welcome to WSA Digital Agency! Type your post caption on the left...</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ======================================================================
     CAMPAIGNS HISTORY & AUDIT MATRIX
     ====================================================================== -->
<div class="card mt-20">
  <div class="cardhead">
    <div>
      <h2>Campaigns &amp; Publishing History</h2>
      <p>Audit trail of all scheduled and instant multi-channel broadcasts</p>
    </div>
    <div class="table-search-bar">
      <input type="search" class="table-search-input" placeholder="Search campaigns..." data-table-filter="campaigns-table" aria-label="Search campaigns">
    </div>
  </div>
  <div class="tablewrap">
    <table id="campaigns-table">
      <thead>
        <tr>
          <th scope="col">Campaign</th>
          <th scope="col">Media</th>
          <th scope="col">Target Channels</th>
          <th scope="col">Timing</th>
          <th scope="col">Auto-Boost</th>
          <th scope="col">Status</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaigns as $c): ?>
          <?php
            $ps = json_decode((string)$c['platforms'], true) ?: [];
            $mVersion = (!empty($c['media_path']) && file_exists($c['media_path'])) ? '?v=' . filemtime($c['media_path']) : '';
            $mHtml = !empty($c['media_path'])
                ? '<a href="' . h($c['media_path']) . '" target="_blank" rel="noopener" class="media-thumb-wrap" title="View attached image"><img src="' . h($c['media_path']) . $mVersion . '" class="media-thumb" alt="Thumbnail" width="44" height="44" loading="lazy"></a>'
                : '<span class="text-muted">-</span>';
            $actHtml = '';
            if (!empty($c['last_job_error'])) {
                $actHtml .= '<form method="post" action="index.php?page=campaign-retry" class="form-inline" style="margin-right:4px;">'
                  . csrf_field()
                  . '<input type="hidden" name="id" value="' . (int)$c['id'] . '">'
                  . '<button type="submit" class="smallbtn" style="background:#2563eb; color:#fff; border-color:#2563eb;">🔄 Retry</button></form>';
            }
            if ($c['status'] === 'scheduled') {
                $actHtml .= '<form method="post" action="index.php?page=campaign-cancel" class="form-inline" data-confirm="Cancel this campaign and its pending scheduled jobs?">'
                  . csrf_field()
                  . '<input type="hidden" name="id" value="' . (int)$c['id'] . '">'
                  . '<button type="submit" class="smallbtn danger">Cancel</button></form>';
            }
            if ($actHtml === '') {
                $actHtml = '<span class="text-muted">-</span>';
            }

            $chipsHtml = '';
            foreach ($ps as $pk) {
                $chipsHtml .= '<span class="platform-chip">' . Icons::platform($pk, 12, 'chip-icon') . ' ' . h(platforms()[$pk][0] ?? $pk) . '</span> ';
            }
            $statusClass = 'status-' . preg_replace('/[^a-z0-9_-]/i', '_', (string)$c['status']);
            $isBoosted = !empty($c['auto_boost']);
          ?>
          <tr>
            <td>
              <b><?= h($c['name']) ?></b>
              <div style="font-size:11px; color:var(--muted); margin-top:2px; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?= h($c['message']) ?>
              </div>
            </td>
            <td><?= $mHtml ?></td>
            <td style="max-width:220px;"><?= $chipsHtml ?: '<span class="text-muted">-</span>' ?></td>
            <td><span style="font-size:11.5px;"><?= h($c['scheduled_for']) ?></span></td>
            <td>
              <?php if ($isBoosted): ?>
                <span class="pill" style="background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:11px;">
                  <?= Icons::svg('zap', 11) ?> Auto-Boosted
                </span>
              <?php else: ?>
                <span class="text-muted" style="font-size:11px;">Standard</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="status <?= $statusClass ?>"><?= h($c['status']) ?></span>
              <?php if (!empty($c['last_job_error'])): ?>
                <?php
                  $isPerm = (stripos($c['last_job_error'], 'pages_manage_posts') !== false || stripos($c['last_job_error'], '(#200)') !== false);
                  $errHint = $isPerm ? 'Meta (#200): pages_manage_posts permission missing. Reconnect Meta in Accounts.' : $c['last_job_error'];
                ?>
                <div style="margin-top:4px;">
                  <span class="pill" style="background:#fee2e2; color:#b91c1c; font-size:10px; font-weight:700; display:inline-block; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= h($errHint) ?>">
                    ⚠️ <?= $isPerm ? 'Meta Permission' : 'Delivery Issue' ?>
                  </span>
                </div>
              <?php endif; ?>
            </td>
            <td><?= $actHtml ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($campaigns)): ?>
          <tr class="empty-row"><td colspan="7" class="empty">No campaigns created yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ======================================================================
     INTERACTIVE JAVASCRIPT
     ====================================================================== -->
<script>
// 1. Text input sync to mockup
var msgInput = document.getElementById('cmp_msg');
var fbMockText = document.getElementById('mockup-fb-text');
var igMockText = document.getElementById('mockup-ig-text');
var charTotal = document.getElementById('char-total-badge');
var limitIg = document.getElementById('limit-ig');
var limitX = document.getElementById('limit-x');

if (msgInput) {
  msgInput.addEventListener('input', function() {
    var val = this.value;
    var len = val.length;
    fbMockText.textContent = val.trim() ? val : "Welcome to WSA Digital Agency! Type your post caption on the left to see live real-time preview...";
    igMockText.textContent = val.trim() ? val : "Welcome to WSA Digital Agency! Type your post caption on the left...";
    charTotal.textContent = len + " characters";
    limitIg.textContent = 2200 - len;
    limitX.textContent = 280 - len;
  });
}

// 2. Extra Channels Drawer Toggle
function toggleExtraChannelsDrawer() {
  var d = document.getElementById('extra-channels-drawer');
  var b = document.getElementById('btn-toggle-extra');
  if (d.style.display === 'none') {
    d.style.display = 'block';
    b.textContent = 'Hide Other Pages';
  } else {
    d.style.display = 'none';
    b.textContent = '+ Other Brand Pages (<?= count($otherAccounts) ?>)';
  }
}

function toggleExtraBoxes(checked) {
  document.querySelectorAll('.extra-acc-cb').forEach(function(cb) {
    cb.checked = checked;
  });
}

// 3. WhatsApp CTA Toggle & Live Preview
function toggleWhatsAppInput(enabled) {
  var fields = document.getElementById('whatsapp_fields');
  var waCard = document.getElementById('mockup-wa-card');
  var numInput = document.getElementById('wa_num');
  fields.style.display = enabled ? 'block' : 'none';
  waCard.style.display = enabled ? 'flex' : 'none';
  if (enabled && numInput.value.trim()) {
    updateWhatsAppPreview(numInput.value);
  }
}

function updateWhatsAppPreview(number) {
  var label = document.getElementById('mockup-wa-label');
  var num = number.trim();
  if (num) {
    label.textContent = "Chat on WhatsApp: +" + num;
  } else {
    label.textContent = "Chat with WSA Digital Agency";
  }
}

// 4. Link Preview Sync
function updateLinkPreview(url) {
  var domainWrap = document.getElementById('mockup-fb-link-domain');
  var cardWrap = document.getElementById('mockup-fb-link-card');
  if (!url || !url.trim()) {
    cardWrap.style.display = 'none';
    return;
  }
  try {
    var parsed = new URL(url.startsWith('http') ? url : 'https://' + url);
    domainWrap.textContent = parsed.hostname.toUpperCase();
    cardWrap.style.display = 'block';
  } catch (e) {
    domainWrap.textContent = url.toUpperCase();
    cardWrap.style.display = 'block';
  }
}

// 5. Media Upload & Preview
function handleMediaSelect(input) {
  if (input.files && input.files[0]) {
    var file = input.files[0];
    var reader = new FileReader();
    reader.onload = function(e) {
      var src = e.target.result;
      document.getElementById('media-preview-img').src = src;
      document.getElementById('media-preview-name').textContent = file.name;
      document.getElementById('media-preview-size').textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
      document.getElementById('media-preview-box').style.display = 'flex';
      document.getElementById('dropzone-prompt').style.display = 'none';
      
      // Facebook mockup image
      document.getElementById('mockup-fb-media').src = src;
      document.getElementById('mockup-fb-media-wrap').style.display = 'block';
      
      // Instagram mockup image
      document.getElementById('mockup-ig-media').src = src;
      document.getElementById('mockup-ig-media').style.display = 'block';
      document.getElementById('mockup-ig-placeholder').style.display = 'none';
    };
    reader.readAsDataURL(file);
  }
}

function removeMediaAttachment() {
  document.getElementById('cmp_media').value = '';
  document.getElementById('media-preview-box').style.display = 'none';
  document.getElementById('dropzone-prompt').style.display = 'flex';
  document.getElementById('mockup-fb-media-wrap').style.display = 'none';
  document.getElementById('mockup-ig-media').style.display = 'none';
  document.getElementById('mockup-ig-placeholder').style.display = 'block';
}

// 6. Device Mockup Switcher
function switchPreviewDevice(device) {
  var fbBtn = document.getElementById('tab-fb-btn');
  var igBtn = document.getElementById('tab-ig-btn');
  var fbMock = document.getElementById('preview-fb');
  var igMock = document.getElementById('preview-ig');
  
  if (device === 'facebook') {
    fbBtn.classList.add('active');
    igBtn.classList.remove('active');
    fbMock.style.display = 'block';
    igMock.style.display = 'none';
  } else {
    fbBtn.classList.remove('active');
    igBtn.classList.add('active');
    fbMock.style.display = 'none';
    igMock.style.display = 'block';
  }
}
</script>
