<?php
declare(strict_types=1);

/**
 * @var array $connectedAccounts
 * @var array $recentComments
 * @var array $recentWsaPosts
 * @var ?array $defaultMainPage
 * @var int $defaultMainPageId
 * @var array $sevenDayPosts
 * @var array $livePagePosts
 * @var array $boosterPages
 */
$csrf = csrf();
$connectedAccounts = $connectedAccounts ?? [];
$recentComments = $recentComments ?? [];
$recentWsaPosts = $recentWsaPosts ?? [];
$defaultMainPage = $defaultMainPage ?? null;
$defaultMainPageId = $defaultMainPageId ?? 24;
$sevenDayPosts = $sevenDayPosts ?? [];
$livePagePosts = $livePagePosts ?? $sevenDayPosts ?? [];
$boosterPages = $boosterPages ?? array_values(array_filter($connectedAccounts, fn($a) => $a['platform'] === 'facebook' && (int)$a['id'] !== (int)$defaultMainPageId));

$fbBoosterAccounts = array_values(array_filter($connectedAccounts, fn($a) => $a['platform'] === 'facebook'));

$defaultCommentsPool = [
    "Outstanding work and strategic execution! Great job WSA! 🚀",
    "WSA Digital Agency always delivers top quality results! 🔥",
    "Highly recommended team for digital marketing and brand scaling! ⭐⭐⭐⭐⭐",
    "Brilliant insights and creativity as always! 👏",
    "Consistent quality and dedicated team. Strongly recommend!",
    "Top digital marketing agency with proven results! 👍",
    "Awesome work, always exceeding expectations! 💯",
    "Superb expertise and dedication by WSA Digital Agency!",
    "Creative excellence and great consistency! Keep shining! ✨",
    "Truly inspiring work! Proud to collaborate with WSA Digital Agency! 🙌",
    "Great strategy and impactful visuals! Love this! 🎯",
    "Remarkable quality and professional execution as always! 🌟"
];
?>

<!-- Clean Minimal Header -->
<div class="hero small" style="margin-bottom: 18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:wrap; gap:14px;">
    <div>
      <h1 style="letter-spacing:-0.02em; margin:0 0 4px; font-size:22px; font-weight:800; display:flex; align-items:center; gap:8px;">
        <span style="color:#10b981; display:inline-flex;"><?= Icons::svg('message-square', 22) ?></span>
        <span>Likes &amp; Comments Booster</span>
      </h1>
      <p style="font-size:13px; color:var(--muted); margin:0;">
        Apne sabhi connected Facebook brand pages se live posts aur reels par supportive comments aur likes boost karein.
      </p>
    </div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <button type="button" class="btn" style="background:#10b981; color:#ffffff; font-weight:700; border-radius:8px; padding:8px 14px; font-size:13px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(16,185,129,0.25);" onclick="switchEngagementTab('schedule')">
        <span>⚡ Boost Any Post / Reel</span>
      </button>
      <a href="index.php?page=comments" class="btn light" style="font-size:13px; padding:8px 12px; text-decoration:none;">
        <span>🔄 Refresh Feed</span>
      </a>
    </div>
  </div>
</div>

<!-- Compact Top Stats (3 Clean Cards) -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
  <div class="card" style="padding:14px 18px; margin:0; display:flex; align-items:center; gap:14px;">
    <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; color:#1877f2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::platform('facebook', 20) ?>
    </div>
    <div>
      <div style="font-size:11.5px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Connected Booster Pages</div>
      <div style="font-size:22px; font-weight:800; color:var(--ink); line-height:1.2;"><?= count($boosterPages) ?> <span style="font-size:12px; font-weight:500; color:var(--muted);">pages</span></div>
    </div>
  </div>

  <div class="card" style="padding:14px 18px; margin:0; display:flex; align-items:center; gap:14px;">
    <div style="width:40px; height:40px; border-radius:10px; background:#fdf2f8; color:#db2777; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::svg('sparkles', 18) ?>
    </div>
    <div>
      <div style="font-size:11.5px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">7-Days Live Posts</div>
      <div style="font-size:22px; font-weight:800; color:var(--ink); line-height:1.2;"><?= count($sevenDayPosts) ?> <span style="font-size:12px; font-weight:500; color:var(--muted);">posts/reels</span></div>
    </div>
  </div>

  <div class="card" style="padding:14px 18px; margin:0; display:flex; align-items:center; gap:14px;">
    <div style="width:40px; height:40px; border-radius:10px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::svg('like', 18) ?>
    </div>
    <div>
      <div style="font-size:11.5px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Total Dispatches Logged</div>
      <div style="font-size:22px; font-weight:800; color:var(--ink); line-height:1.2;"><?= count($recentComments) ?> <span style="font-size:12px; font-weight:500; color:var(--muted);">interactions</span></div>
    </div>
  </div>
</div>

<!-- 🎯 DEFAULT MAIN FACEBOOK PAGE BANNER -->
<div style="background:#ffffff; border:1px solid #bfdbfe; border-left:4px solid #1877f2; border-radius:10px; padding:12px 18px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; box-shadow:0 2px 6px rgba(24,119,242,0.06);">
  <div style="display:flex; align-items:center; gap:12px;">
    <div style="width:36px; height:36px; border-radius:8px; background:#eff6ff; color:#1877f2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::platform('facebook', 20) ?>
    </div>
    <div>
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:11px; font-weight:700; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.04em;">Target Main Facebook Page:</span>
        <span style="font-size:10px; font-weight:700; background:#dcfce7; color:#166534; padding:1px 7px; border-radius:12px;">ACTIVE DEFAULT</span>
      </div>
      <div style="font-size:15px; font-weight:800; color:var(--ink); margin-top:2px;">
        <?= h($defaultMainPage['label'] ?? 'WSA Digital Agency (Page)') ?>
        <span style="font-size:11.5px; font-weight:500; color:var(--muted); margin-left:6px;">(Page ID: <code><?= h($defaultMainPage['external_id'] ?? '573548745852007') ?></code>)</span>
      </div>
    </div>
  </div>
  <div style="display:flex; align-items:center; gap:8px;">
    <a href="index.php?page=groups" class="btn light" style="font-size:12px; padding:6px 12px; border-radius:6px; text-decoration:none;">
      🔄 Switch Default Page in Settings
    </a>
  </div>
</div>

<!-- Main Section Tabs (Simple & Focused) -->
<div class="account-filter-tabs" style="margin-bottom:16px; border-bottom:1px solid var(--line); padding-bottom:8px; gap:8px;">
  <button type="button" class="tab-pill comments-main-tab-btn active" id="tab-btn-reels" onclick="switchEngagementTab('reels')">
    🔥 7-Days Reels &amp; Posts (<?= count($sevenDayPosts) ?>)
  </button>
  <button type="button" class="tab-pill comments-main-tab-btn" id="tab-btn-schedule" onclick="switchEngagementTab('schedule')">
    🚀 Boost Any Custom Post / Link
  </button>
  <button type="button" class="tab-pill comments-main-tab-btn" id="tab-btn-pages" onclick="switchEngagementTab('pages')">
    👥 Connected Booster Pages (<?= count($boosterPages) ?>)
  </button>
  <button type="button" class="tab-pill comments-main-tab-btn" id="tab-btn-history" onclick="switchEngagementTab('history')">
    📜 Engagement History (<?= count($recentComments) ?>)
  </button>
</div>

<!-- ===================================================================== -->
<!-- TAB 1: 7-DAYS REELS & POSTS (LIVE FEED WITH 1-CLICK BOOST)            -->
<!-- ===================================================================== -->
<div id="tab-pane-reels" class="comments-main-tab-pane">
  <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
      <h2 style="font-size:16px; font-weight:800; margin:0 0 4px; display:flex; align-items:center; gap:8px;">
        <span>🔥 <?= h($defaultMainPage['label'] ?? 'WSA Digital Agency') ?> — Last 7 Days Feed</span>
        <span style="font-size:11px; font-weight:700; background:#eff6ff; color:#1d4ed8; padding:2px 8px; border-radius:12px;"><?= count($sevenDayPosts) ?> Posts &amp; Reels</span>
      </h2>
      <p style="font-size:12.5px; color:var(--muted); margin:0;">
        Pichle 7 dino ki live posts aur reels. Kisi bhi post par click karein aur sabhi <?= count($boosterPages) ?> pages se 1-click me likes &amp; comments drop karein.
      </p>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:16px; margin-bottom:28px;">
    <?php foreach ($sevenDayPosts as $idx => $post): ?>
      <?php 
        $postUrl = MetaGraph::resolvePostUrl($post, (string)($defaultMainPage['external_id'] ?? ''));
        $isReel = (str_contains($postUrl, '/reel/') || str_contains($postUrl, '/watch/') || ($post['status_type'] ?? '') === 'added_video');
        $postCaption = trim((string)($post['message'] ?? ''));
        $createdTime = !empty($post['created_time']) ? date('d M Y, h:i A', strtotime($post['created_time'])) : 'Recent';
      ?>
      <div class="card" style="margin:0; padding:18px; display:flex; flex-direction:column; justify-content:space-between; border-radius:12px; border:1px solid var(--line); box-shadow:0 2px 8px rgba(0,0,0,0.03); background:#ffffff;">
        <div>
          <!-- Header: Type Badge & Date -->
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div style="display:flex; align-items:center; gap:6px;">
              <?php if ($isReel): ?>
                <span style="font-size:11px; font-weight:700; background:#fdf2f8; color:#db2777; border:1px solid #fbcfe8; padding:2px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                  <span>🎬 Facebook Reel</span>
                </span>
              <?php else: ?>
                <span style="font-size:11px; font-weight:700; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:2px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                  <span>📄 Post</span>
                </span>
              <?php endif; ?>
              <span style="font-size:11px; color:var(--muted);"><?= h($createdTime) ?></span>
            </div>
            <a href="<?= h($postUrl) ?>" target="_blank" rel="noopener" style="font-size:11px; color:#1877f2; font-weight:700; text-decoration:none;">
              View FB ↗
            </a>
          </div>

          <!-- Caption Text -->
          <div style="font-size:13px; line-height:1.5; color:var(--ink); margin-bottom:14px; max-height:120px; overflow-y:auto; word-break:break-word;">
            <?= nl2br(h($postCaption ?: 'Facebook post without caption text.')) ?>
          </div>
        </div>

        <!-- Action Footer: Boost Modal Button -->
        <div style="border-top:1px solid var(--line); padding-top:12px; margin-top:8px;">
          <div style="display:flex; gap:8px;">
            <button type="button" class="btn" 
              data-post-id="<?= h($post['id']) ?>" 
              data-post-url="<?= h($postUrl) ?>" 
              data-snippet="<?= h(preg_replace('/\s+/', ' ', mb_strimwidth($postCaption ?: 'Facebook Post/Reel', 0, 75, '...'))) ?>" 
              onclick="handleBoostButtonClick(this)" 
              style="flex:1; justify-content:center; background:#10b981; color:#ffffff; font-size:12.5px; font-weight:700; padding:9px 12px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(16,185,129,0.25);">
              <span>💬 Boost Likes &amp; Comments (<?= count($boosterPages) ?> Pages)</span>
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($sevenDayPosts)): ?>
      <div style="grid-column: 1 / -1; background:#ffffff; border:1px solid var(--line); border-radius:12px; padding:32px; text-align:center; color:var(--muted);">
        <div style="font-size:24px; margin-bottom:8px;">📭</div>
        <div style="font-weight:700; font-size:14px; color:var(--ink);">Pichle 7 dino me koi nayi post nahi mili</div>
        <div style="font-size:12px; margin-top:4px;">WSA Digital Agency page par nayi post/reel aate hi automatically yahan reflect ho jayegi.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===================================================================== -->
<!-- TAB 2: BOOST ANY CUSTOM POST / LINK                                   -->
<!-- ===================================================================== -->
<div id="tab-pane-schedule" class="comments-main-tab-pane" style="display:none;">
  <div class="card" style="max-width:760px; margin:0 auto 24px;">
    <div class="cardhead" style="margin-bottom:16px; border-bottom:1px solid var(--line); padding-bottom:12px;">
      <div>
        <h2 style="font-size:16px; font-weight:800; margin:0; display:flex; align-items:center; gap:8px;">
          <?= Icons::svg('sparkles', 18, 'text-blue') ?>
          <span>Kisi Bhi Post / Reel Par Like &amp; Comment Boost Karein</span>
        </h2>
        <p style="font-size:12px; color:var(--muted); margin:4px 0 0;">
          Select target Facebook post/reel and drop supportive comments and likes from your selected brand pages.
        </p>
      </div>
    </div>

    <form method="post" action="index.php?page=boost-comments">
      <?= csrf_field() ?>

      <!-- 1. Post Selection -->
      <label for="boost_post_select" style="font-weight:700; font-size:13px;">1. Kaunsi Post / Reel Boost Karni Hai:
        <select id="boost_post_select" style="margin-top:6px; font-weight:600; padding:9px 12px; border-radius:8px;" onchange="onBoostPostSelectChange(this)">
          <optgroup label="🎬 WSA Digital Agency — Last 7 Days Feed">
            <?php foreach ($livePagePosts as $lp): ?>
              <?php 
                $pUrl = MetaGraph::resolvePostUrl($lp, (string)($defaultMainPage['external_id'] ?? ''));
                $isReel = (str_contains($pUrl, '/reel/') || str_contains($pUrl, '/watch/') || ($lp['status_type'] ?? '') === 'added_video');
                $dateStr = !empty($lp['created_time']) ? date('d M, h:i A', strtotime($lp['created_time'])) : '';
                $snip = mb_strimwidth(trim($lp['message'] ?? 'Facebook Post'), 0, 70, '...');
              ?>
              <option value="<?= h($lp['id']) ?>" data-url="<?= h($pUrl) ?>">
                <?= $isReel ? '🎬 Reel' : '📄 Post' ?> (<?= h($dateStr) ?>): <?= h($snip) ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
          <option value="custom">🔗 Ya Niche Custom Link / Post ID Daalein</option>
        </select>
      </label>

      <div style="margin-top:12px;">
        <label for="boost_target_id" style="font-size:12px; font-weight:700; color:var(--ink);">Post Direct URL ya Meta Post ID:
          <input id="boost_target_id" name="target_post_id" required placeholder="https://www.facebook.com/... ya Meta Post ID" style="margin-top:4px;" value="<?= !empty($livePagePosts[0]['id']) ? h($livePagePosts[0]['id']) : '' ?>">
        </label>
        <input type="hidden" id="boost_target_url" name="target_post_url" value="<?= !empty($livePagePosts[0]) ? h(MetaGraph::resolvePostUrl($livePagePosts[0], (string)($defaultMainPage['external_id'] ?? ''))) : '' ?>">
      </div>

      <!-- 2. Booster Pages Checklist -->
      <div style="margin-top:16px; background:#f8fafc; border:1px solid var(--line); border-radius:10px; padding:14px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
          <div>
            <label style="font-weight:700; font-size:13px; color:var(--ink); margin:0;">
              2. Kaun-Kaun Se Pages Se Like &amp; Comment Karna Hai (<span id="selected-booster-count"><?= count($boosterPages) ?></span> Selected):
            </label>
            <div style="font-size:11.5px; color:var(--muted); margin-top:2px;">
              Selected pages aapki post par automatically comments aur likes send karenge.
            </div>
          </div>
          <button type="button" class="smallbtn" onclick="toggleAllBoosterPages()" style="font-size:11px; padding:4px 10px; background:#eff6ff; color:#1877f2; border:1px solid #bfdbfe; font-weight:700;">
            Select / Deselect All
          </button>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:8px; max-height:180px; overflow-y:auto; padding:2px;">
          <?php foreach ($boosterPages as $bp): ?>
            <label style="display:flex; align-items:center; gap:8px; background:#ffffff; border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:12px; font-weight:600; cursor:pointer;" title="Page ID: <?= h($bp['external_id']) ?>">
              <input type="checkbox" name="booster_account_ids[]" value="<?= (int)$bp['id'] ?>" class="booster-page-checkbox" checked onchange="updateBoosterCount()" style="width:16px; height:16px; cursor:pointer;">
              <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= h($bp['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 3. Comments Pool -->
      <div style="margin-top:16px;">
        <label for="boost_pool" style="font-weight:700; margin-bottom:6px; font-size:13px; display:block;">
          3. Supportive Comments Pool (1 Line = 1 Page Comment):
        </label>
        <textarea id="boost_pool" name="comments_pool" rows="6" style="font-size:12px; line-height:1.45; padding:10px 14px; border-radius:8px;" required><?= h(implode("\n", $defaultCommentsPool)) ?></textarea>
        <small style="font-size:11.5px; color:var(--muted); display:block; margin-top:4px;">
          🔄 Har page ko alag-alag unique comment assign hoga taaki organic aur genuine lage.
        </small>
      </div>

      <!-- 4. Engagement Options -->
      <div style="margin-top:16px; background:#f0fdf4; border:1px solid #bbf7d0; padding:12px 16px; border-radius:8px;">
        <label style="display:inline-flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; font-size:13px; margin:0; color:#166534;">
          <input type="checkbox" name="do_like" value="1" checked style="width:17px; height:17px; accent-color:#10b981;">
          <span style="display:flex; align-items:center; gap:8px;">
            <?= Icons::svg('like', 16) ?>
            <span>Har brand page se post par Like bhi automatic drop karein</span>
          </span>
        </label>
      </div>

      <button type="submit" class="btn" style="width:100%; justify-content:center; padding:12px 16px; font-weight:700; font-size:14px; background:#10b981; color:#fff; border-radius:8px; margin-top:18px; box-shadow:0 2px 8px rgba(16,185,129,0.3);">
        <?= Icons::svg('sparkles', 16) ?>
        <span>Launch Multi-Page Likes &amp; Comments Boost</span>
      </button>
    </form>
  </div>
</div>

<!-- ===================================================================== -->
<!-- TAB 3: CONNECTED BOOSTER PAGES                                         -->
<!-- ===================================================================== -->
<div id="tab-pane-pages" class="comments-main-tab-pane" style="display:none;">
  <div class="card" style="margin-bottom: 24px;">
    <div style="margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:10px;">
      <h2 style="font-size:16px; font-weight:800; margin:0 0 4px;">👥 Connected Facebook Booster Pages (<?= count($boosterPages) ?>)</h2>
      <p style="font-size:12.5px; color:var(--muted); margin:0;">
        Ye sabhi brand pages aapke main page ki posts aur reels par supportive comments aur likes boost karne ke liye ready hain.
      </p>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">
      <?php foreach ($boosterPages as $bp): ?>
        <div style="background:#ffffff; border:1px solid var(--line); border-radius:10px; padding:14px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <div style="display:flex; align-items:center; gap:10px; min-width:0;">
            <div style="width:36px; height:36px; border-radius:8px; background:#eff6ff; color:#1877f2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
              <?= Icons::platform('facebook', 18) ?>
            </div>
            <div style="min-width:0;">
              <div style="font-weight:700; font-size:13px; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?= h($bp['label']) ?>
              </div>
              <div style="font-size:11px; color:var(--muted); margin-top:2px;">
                Page ID: <code><?= h($bp['external_id']) ?></code>
              </div>
            </div>
          </div>
          <span style="font-size:10.5px; font-weight:700; background:#dcfce7; color:#166534; padding:2px 8px; border-radius:12px; flex-shrink:0;">
            READY
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ===================================================================== -->
<!-- TAB 4: ENGAGEMENT & DELIVERY HISTORY                                   -->
<!-- ===================================================================== -->
<div id="tab-pane-history" class="comments-main-tab-pane" style="display:none;">
  <div class="card" style="margin-bottom: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom:1px solid var(--line); padding-bottom:12px; margin-bottom:14px;">
      <div>
        <h2 style="font-size:16px; font-weight:800; margin:0 0 4px;">📜 Engagement &amp; Comments Delivery Log</h2>
        <p style="font-size:12px; color:var(--muted); margin:0;">Complete audit trail of all brand interactions and supportive comments.</p>
      </div>
      <div style="display:flex; gap:8px; align-items:center;">
        <input type="search" class="table-search-input" placeholder="🔍 Search comments..." data-table-filter="comments-table" aria-label="Search comments" style="font-size:12px; padding:6px 12px; border-radius:6px; width:200px;">
        <form method="post" action="index.php?page=comment-logs-clear" data-confirm="Clear all failed previous attempts from log?" style="margin:0;">
          <?= csrf_field() ?>
          <button type="submit" class="smallbtn" style="background:var(--bg); border:1px solid var(--line); color:var(--muted); font-size:11.5px; padding:6px 12px; cursor:pointer; border-radius:6px; font-weight:600;">
            🧹 Clear Failed
          </button>
        </form>
      </div>
    </div>

    <div class="tablewrap" style="border:none; border-radius:0;">
      <table id="comments-table">
        <thead>
          <tr>
            <th scope="col">Booster Page</th>
            <th scope="col">Target Post</th>
            <th scope="col">Comment Text</th>
            <th scope="col">Status</th>
            <th scope="col">Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentComments as $cm): ?>
            <?php
              $isOk = ($cm['status'] === 'published');
              $badge = $isOk 
                  ? '<span class="badge-active" style="padding:3px 8px; font-size:11px;">Published ✓</span>' 
                  : '<span class="badge-inactive" style="background:#fee2e2;color:#991b1b; padding:3px 8px; font-size:11px;" title="' . h($cm['error_message'] ?? 'Error') . '">Deferred (Token Expired)</span>';
            ?>
            <tr>
              <td>
                <div style="display:flex; align-items:center; gap:8px;">
                  <span style="color:#1877f2; display:flex; align-items:center;"><?= Icons::platform('facebook', 14) ?></span>
                  <b style="font-size:12.5px;"><?= h($cm['account_label'] ?? 'Unknown Account') ?></b>
                </div>
              </td>
              <td><code style="font-size:11px; background:var(--bg); padding:2px 6px; border-radius:4px;"><?= h($cm['post_external_id']) ?></code></td>
              <td style="max-width:320px; font-size:12px; line-height:1.4;"><?= h($cm['comment_text']) ?></td>
              <td><?= $badge ?></td>
              <td><span style="font-size:11.5px; color:var(--muted);"><?= h($cm['created_at']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($recentComments)): ?>
            <tr class="empty-row"><td colspan="5" class="empty">Abhi tak koi comments dispatch nahi hue hain.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL: 1-CLICK LIKE & COMMENT BOOSTER STATION                          -->
<!-- ===================================================================== -->
<dialog id="modal-boost-post" style="border:1px solid var(--line); border-radius:14px; padding:24px; max-width:620px; width:92%; background:var(--bg); color:var(--ink); box-shadow:0 15px 35px rgba(0,0,0,0.25);">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:10px;">
    <div>
      <h3 style="font-size:16px; font-weight:800; margin:0 0 2px; display:flex; align-items:center; gap:6px;">
        <span style="color:#10b981;"><?= Icons::svg('sparkles', 18) ?></span>
        <span>1-Click Like &amp; Comment Booster Station</span>
      </h3>
      <p style="font-size:12px; color:var(--muted); margin:0;">
        Apne sabhi connected pages se post par unique comments aur likes drop karein.
      </p>
    </div>
    <button type="button" class="smallbtn" onclick="document.getElementById('modal-boost-post').close()" style="background:transparent; border:none; font-size:16px; cursor:pointer;">✕</button>
  </div>

  <!-- Selected Post Info -->
  <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:12px;">
    <div style="font-weight:700; color:#1e40af; margin-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
      <span>Target Facebook Post / Reel:</span>
      <a id="boost_modal_link" href="#" target="_blank" rel="noopener" style="font-size:11px; color:#1877f2; font-weight:700; text-decoration:none;">View FB ↗</a>
    </div>
    <div id="boost_modal_snippet" style="color:#1e3a8a; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
      Post Caption
    </div>
  </div>

  <!-- Individual Page Copy & Open Station -->
  <div style="margin-bottom:16px;">
    <div style="font-size:12px; font-weight:700; color:var(--ink); margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
      <span>👥 Aapke Connected Pages Ke Liye Unique Comments:</span>
      <span style="font-size:11px; color:var(--muted); font-weight:normal;"><?= count($boosterPages) ?> Pages Available</span>
    </div>

    <div style="display:flex; flex-direction:column; gap:8px; max-height:240px; overflow-y:auto; padding-right:4px;">
      <?php foreach ($boosterPages as $bIdx => $bp): ?>
        <?php $comm = $defaultCommentsPool[$bIdx % count($defaultCommentsPool)]; ?>
        <div style="background:#ffffff; border:1px solid var(--line); border-radius:8px; padding:8px 12px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
          <div style="min-width:0; flex:1;">
            <div style="font-size:11.5px; font-weight:700; color:var(--ink); display:flex; align-items:center; gap:6px;">
              <span style="color:#1877f2;"><?= Icons::platform('facebook', 13) ?></span>
              <span><?= h($bp['label']) ?></span>
            </div>
            <div style="font-size:11px; color:var(--muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= h($comm) ?>">
              💬 "<?= h($comm) ?>"
            </div>
          </div>
          <button type="button" class="smallbtn" style="background:#eff6ff; color:#1d4ed8; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px; flex-shrink:0;" onclick="copyAndOpenPageComment(this, '<?= h(addslashes($comm)) ?>', '<?= h(addslashes($bp['label'])) ?>')">
            📋 Copy &amp; Open
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Automated 1-Click API Dispatch -->
  <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
      <div style="font-size:12px; font-weight:700; color:#166534;">🚀 1-Click Automated API Boost:</div>
      <div style="font-size:11px; color:#15803d; margin-top:2px;">
        Sabhi <?= count($boosterPages) ?> pages se automatically backend se Like aur Comment request send karein.
      </div>
    </div>
    <form method="post" action="index.php?page=boost-comments" style="margin:0;">
      <?= csrf_field() ?>
      <input type="hidden" id="modal_api_post_id" name="target_post_id" value="">
      <input type="hidden" id="modal_api_post_url" name="target_post_url" value="">
      <input type="hidden" name="do_like" value="1">
      <?php foreach ($boosterPages as $bp): ?>
        <input type="hidden" name="booster_account_ids[]" value="<?= (int)$bp['id'] ?>">
      <?php endforeach; ?>
      <button type="submit" class="btn" style="background:#10b981; color:#fff; font-weight:700; font-size:12px; padding:7px 14px; border-radius:6px;">
        ⚡ Dispatch API Boost Now
      </button>
    </form>
  </div>

  <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid var(--line); font-size:11px; color:var(--muted);">
    <div>💡 Tip: Facebook par top-right se page profile switch karke 2 second me comment drop kar sakte hain.</div>
    <button type="button" class="btn light" onclick="document.getElementById('modal-boost-post').close()" style="font-size:12px; padding:5px 12px;">Close</button>
  </div>
</dialog>

<script>
let currentBoostUrl = '';
let currentBoostId = '';

function handleBoostButtonClick(btn) {
  const postId = btn.getAttribute('data-post-id') || '';
  const postUrl = btn.getAttribute('data-post-url') || '';
  const snippet = btn.getAttribute('data-snippet') || 'Facebook Post/Reel';
  openBoostModal(postId, postUrl, snippet);
}

function openBoostModal(postId, postUrl, snippet) {
  currentBoostId = postId;
  currentBoostUrl = postUrl;
  const snippetEl = document.getElementById('boost_modal_snippet');
  if (snippetEl) snippetEl.innerText = snippet || 'Facebook Post/Reel';
  const linkEl = document.getElementById('boost_modal_link');
  if (linkEl) linkEl.href = postUrl || '#';
  const inputId = document.getElementById('modal_api_post_id');
  if (inputId) inputId.value = postId || '';
  const inputUrl = document.getElementById('modal_api_post_url');
  if (inputUrl) inputUrl.value = postUrl || '';
  const modal = document.getElementById('modal-boost-post');
  if (modal) modal.showModal();
}

function copyAndOpenPageComment(btn, commentText, pageLabel) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(commentText).then(function() {
      const orig = btn.innerText;
      btn.innerText = 'Copied! ✓';
      btn.style.background = '#10b981';
      btn.style.color = '#fff';
      setTimeout(function() {
        btn.innerText = orig;
        btn.style.background = '#eff6ff';
        btn.style.color = '#1d4ed8';
      }, 3000);
    });
  }
  if (currentBoostUrl) {
    window.open(currentBoostUrl, '_blank');
  }
}

function onBoostPostSelectChange(sel) {
  const val = sel.value;
  const targetIdInput = document.getElementById('boost_target_id');
  const targetUrlInput = document.getElementById('boost_target_url');
  if (val === 'custom') {
    if (targetIdInput) {
      targetIdInput.value = '';
      targetIdInput.focus();
    }
    if (targetUrlInput) targetUrlInput.value = '';
  } else {
    if (targetIdInput) targetIdInput.value = val;
    const selectedOption = sel.options[sel.selectedIndex];
    const postUrl = selectedOption.getAttribute('data-url') || '';
    if (targetUrlInput) targetUrlInput.value = postUrl;
  }
}

function updateBoosterCount() {
  const count = document.querySelectorAll('.booster-page-checkbox:checked').length;
  const countEl = document.getElementById('selected-booster-count');
  if (countEl) {
    countEl.textContent = count;
  }
}

function toggleAllBoosterPages() {
  const cbs = document.querySelectorAll('.booster-page-checkbox');
  const allChecked = Array.from(cbs).every(cb => cb.checked);
  cbs.forEach(cb => cb.checked = !allChecked);
  updateBoosterCount();
}

// Main Tabs Switching (Simple & Instant)
function switchEngagementTab(tabName) {
  if (!tabName) tabName = 'reels';
  const activeBtn = document.getElementById('tab-btn-' + tabName);
  const activePane = document.getElementById('tab-pane-' + tabName);
  if (!activePane) return;

  document.querySelectorAll('.comments-main-tab-btn').forEach(function(b) {
    b.classList.remove('active');
  });
  document.querySelectorAll('.comments-main-tab-pane').forEach(function(p) {
    p.style.display = 'none';
  });

  if (activeBtn) activeBtn.classList.add('active');
  activePane.style.display = 'block';

  try { 
    localStorage.setItem('wsa_comments_tab', tabName);
    const u = new URL(window.location.href);
    u.searchParams.set('tab', tabName);
    window.history.replaceState(null, '', u.toString());
  } catch(e) {}
}

// Restore last open tab
(function() {
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && document.getElementById('tab-pane-' + tabParam)) {
      switchEngagementTab(tabParam);
      return;
    }
    const saved = localStorage.getItem('wsa_comments_tab');
    if (saved && document.getElementById('tab-pane-' + saved)) {
      switchEngagementTab(saved);
      return;
    }
    switchEngagementTab('reels');
  } catch(e) {}
})();
</script>
