<?php
declare(strict_types=1);

/**
 * @var array $senderProfiles
 * @var array $targetGroups
 * @var array $shareQueue
 * @var array $publishedCampaigns
 * @var array $niches
 * @var array $stats
 * @var array $connectedFbAccounts
 * @var array $accountGroupCounts
 * @var int $totalGroupsCount
 * @var ?int $filterAccountId
 */
$csrf = csrf();
$connectedFbAccounts = $connectedFbAccounts ?? [];
$accountGroupCounts = $accountGroupCounts ?? [];
$totalGroupsCount = $totalGroupsCount ?? count($targetGroups);
$pendingQueue = array_values(array_filter($shareQueue, fn($q) => ($q['status'] ?? '') === 'queued'));
$publishedQueue = array_values(array_filter($shareQueue, fn($q) => in_array($q['status'] ?? '', ['shared', 'pending_approval'])));
$pendingApprovalQueue = array_values(array_filter($shareQueue, fn($q) => ($q['status'] ?? '') === 'pending_approval'));
$liveSharedQueue = array_values(array_filter($shareQueue, fn($q) => ($q['status'] ?? '') === 'shared'));
$failedQueue = array_values(array_filter($shareQueue, fn($q) => ($q['status'] ?? '') === 'failed'));

// Auto-Pilot Configuration & State
$autoPilotEnabled = $autoPilotEnabled ?? true;
$autoPilotCount = $autoPilotCount ?? 5;
$autoPilotInterval = $autoPilotInterval ?? 30;
$autoPilotLastRun = $autoPilotLastRun ?? '';
$autoPilotAccountId = $autoPilotAccountId ?? 24;
$autoPilotLastPostUrl = $autoPilotLastPostUrl ?? '';
$cachedLatestPost = $cachedLatestPost ?? null;
$isTodayQueued = ($autoPilotLastRun === date('Y-m-d'));

// Default Main Page & 7-Day Posts/Reels
$defaultMainPage = $defaultMainPage ?? null;
$sevenDayPosts = $sevenDayPosts ?? [];
$defaultMainPageId = $defaultMainPageId ?? 24;
$livePagePosts = $livePagePosts ?? $sevenDayPosts ?? [];
$boosterPages = array_values(array_filter($connectedFbAccounts, fn($a) => (int)$a['id'] !== (int)$defaultMainPageId));
?>

<!-- Clean Minimal Header -->
<div class="hero small" style="margin-bottom: 18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:wrap; gap:14px;">
    <div>
      <h1 style="letter-spacing:-0.02em; margin:0 0 4px; font-size:22px; font-weight:800; display:flex; align-items:center; gap:8px;">
        <span style="color:#1877f2; display:inline-flex;"><?= Icons::platform('facebook', 22) ?></span>
        <span>Facebook Groups Manager</span>
      </h1>
      <p style="font-size:13px; color:var(--muted); margin:0;">
        Apne connected Facebook profiles ke joined groups manage karein, posts schedule karein aur safe cadence ke sath share karein.
      </p>
    </div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <button type="button" class="btn" style="background:#1877f2; color:#ffffff; font-weight:700; border-radius:8px; padding:8px 14px; font-size:13px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(24,119,242,0.25);" onclick="document.getElementById('modal-sync-profile-groups').showModal()">
        <span>📥 Sync / Import Groups</span>
      </button>
      <button type="button" class="btn light" style="font-size:13px; padding:8px 12px;" onclick="openAddGroupModal('single')">
        <?= Icons::svg('plus', 14) ?> <span>Add Single Group</span>
      </button>
      <button type="button" class="btn light" style="font-size:13px; padding:8px 12px;" onclick="openAddGroupModal('bulk')">
        <?= Icons::svg('sparkles', 14) ?> <span>Bulk CSV</span>
      </button>
    </div>
  </div>
</div>

<!-- Compact Top Stats (3 Clean Cards) -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
  <div class="card" style="padding:14px 18px; margin:0; display:flex; align-items:center; gap:14px;">
    <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; color:#1d4ed8; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::platform('facebook', 20) ?>
    </div>
    <div>
      <div style="font-size:11.5px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Joined Groups</div>
      <div style="font-size:22px; font-weight:800; color:var(--ink); line-height:1.2;"><?= (int)$totalGroupsCount ?> <span style="font-size:12px; font-weight:500; color:var(--muted);">groups</span></div>
    </div>
  </div>

  <div class="card" style="padding:14px 18px; margin:0; display:flex; align-items:center; gap:14px;">
    <div style="width:40px; height:40px; border-radius:10px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::svg('user', 18) ?>
    </div>
    <div>
      <div style="font-size:11.5px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Connected Profiles</div>
      <div style="font-size:22px; font-weight:800; color:var(--ink); line-height:1.2;"><?= count($connectedFbAccounts) ?> <span style="font-size:12px; font-weight:500; color:var(--muted);">accounts</span></div>
    </div>
  </div>

  <div class="card" style="padding:14px 18px; margin:0; display:flex; align-items:center; gap:14px;">
    <div style="width:40px; height:40px; border-radius:10px; background:#f5f3ff; color:#7c3aed; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?= Icons::svg('share', 18) ?>
    </div>
    <div>
      <div style="font-size:11.5px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Shares Done Today</div>
      <div style="font-size:22px; font-weight:800; color:var(--ink); line-height:1.2;"><?= (int)$stats['total_shares_today'] ?> <span style="font-size:12px; font-weight:500; color:var(--muted);">/ <?= (int)$stats['target_goal'] ?></span></div>
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
        <span style="font-size:11px; font-weight:700; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.04em;">Default Main Facebook Page:</span>
        <span style="font-size:10px; font-weight:700; background:#dcfce7; color:#166534; padding:1px 7px; border-radius:12px;">ACTIVE DEFAULT</span>
      </div>
      <div style="font-size:15px; font-weight:800; color:var(--ink); margin-top:2px;">
        <?= h($defaultMainPage['label'] ?? 'WSA Digital Agency (Page)') ?>
        <span style="font-size:11.5px; font-weight:500; color:var(--muted); margin-left:6px;">(Page ID: <code><?= h($defaultMainPage['external_id'] ?? '573548745852007') ?></code>)</span>
      </div>
    </div>
  </div>
  <div style="display:flex; align-items:center; gap:8px;">
    <button type="button" class="btn light" onclick="document.getElementById('modal-change-default-page').showModal()" style="font-size:12px; padding:6px 12px; border-radius:6px;">
      🔄 Change Default Page
    </button>
  </div>
</div>

<!-- Main Section Tabs (Simple & Focused) -->
<div class="account-filter-tabs" style="margin-bottom:16px; border-bottom:1px solid var(--line); padding-bottom:8px; gap:8px;">
  <button type="button" class="tab-pill groups-main-tab-btn active" id="tab-btn-reels" onclick="switchMainTab('reels')">
    🔥 7-Days Reels &amp; Posts (<?= count($sevenDayPosts) ?>)
  </button>
  <button type="button" class="tab-pill groups-main-tab-btn" id="tab-btn-pool" onclick="switchMainTab('pool')">
    📁 All Groups Pool (<?= (int)$totalGroupsCount ?>)
  </button>
  <button type="button" class="tab-pill groups-main-tab-btn" id="tab-btn-schedule" onclick="switchMainTab('schedule')">
    🚀 Schedule Post to Groups
  </button>
  <button type="button" class="tab-pill groups-main-tab-btn" id="tab-btn-queue" onclick="switchMainTab('queue')">
    ⏳ Queue &amp; History (<?= count($pendingQueue) ?> pending)
  </button>
  <button type="button" class="tab-pill groups-main-tab-btn" id="tab-btn-profiles" onclick="switchMainTab('profiles')">
    👤 Sender Profiles (<?= count($senderProfiles) ?>)
  </button>
</div>

<!-- ===================================================================== -->
<!-- TAB 0: 7-DAYS REELS & POSTS (WSA DIGITAL AGENCY LIVE FEED)             -->
<!-- ===================================================================== -->
<div id="tab-pane-reels" class="groups-main-tab-pane">
  <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
      <h2 style="font-size:16px; font-weight:800; margin:0 0 4px; display:flex; align-items:center; gap:8px;">
        <span>🔥 <?= h($defaultMainPage['label'] ?? 'WSA Digital Agency') ?> — Last 7 Days Feed</span>
        <span style="font-size:11px; font-weight:700; background:#eff6ff; color:#1d4ed8; padding:2px 8px; border-radius:12px;"><?= count($sevenDayPosts) ?> Posts &amp; Reels</span>
      </h2>
      <p style="font-size:12.5px; color:var(--muted); margin:0;">
        Pichle 7 dino ki sabhi live reels aur posts. Yahan se seedha 5 groups me share karein aur connected accounts se likes &amp; comments boost karein.
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

        <!-- Action Footer -->
        <div style="border-top:1px solid var(--line); padding-top:12px; margin-top:8px;">
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <!-- 1-Click Share to 5 Groups -->
            <form method="post" action="index.php?page=group-queue-generate" style="flex:1; margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="custom_post_url" value="<?= h($postUrl) ?>">
              <input type="hidden" name="caption" value="<?= h($postCaption) ?>">
              <input type="hidden" name="target_shares" value="5">
              <input type="hidden" name="interval_minutes" value="30">
              <input type="hidden" name="niche_category" value="all">
              <button type="submit" class="btn" style="width:100%; justify-content:center; background:#1877f2; color:#fff; font-size:11.5px; font-weight:700; padding:8px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                <span>⚡ Share to 5 Groups</span>
              </button>
            </form>

            <!-- Like & Comment Booster Modal Trigger -->
            <button type="button" class="btn light" onclick="openBoostModal('<?= h(addslashes($post['id'])) ?>', '<?= h(addslashes($postUrl)) ?>', '<?= h(addslashes(mb_strimwidth($postCaption, 0, 70, '...'))) ?>')" style="flex:1; justify-content:center; font-size:11.5px; font-weight:700; padding:8px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:4px; color:#059669; border-color:#a7f3d0; background:#f0fdf4;">
              <span>💬 Like &amp; Comment</span>
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
<!-- TAB 1: ALL GROUPS POOL (USER-FRIENDLY TABLE)                           -->
<!-- ===================================================================== -->
<div id="tab-pane-pool" class="groups-main-tab-pane" style="display:none;">
  <div class="card" style="margin-bottom: 24px;">
    <!-- Clean Search & Filter Toolbar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--line);">
      <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <!-- Account Dropdown Filter -->
        <label for="filter_account_select" style="font-size:12px; font-weight:700; margin:0; display:flex; align-items:center; gap:6px;">
          <span>Account:</span>
          <select id="filter_account_select" style="padding:6px 10px; font-size:12.5px; border-radius:6px; margin:0;" onchange="handleAccountDropdownFilter(this.value)">
            <option value="all">🌐 Sabhi Facebook Profiles (<?= (int)$totalGroupsCount ?> Groups)</option>
            <?php foreach ($connectedFbAccounts as $fbAcc): ?>
              <?php 
                $gCnt = (int)($accountGroupCounts[$fbAcc['id']] ?? 0); 
                $selected = ($filterAccountId === (int)$fbAcc['id']) ? 'selected' : '';
              ?>
              <option value="<?= (int)$fbAcc['id'] ?>" <?= $selected ?>>
                <?= h($fbAcc['label']) ?> (<?= $gCnt ?> groups)
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Category Filter -->
        <label for="filter_niche_select" style="font-size:12px; font-weight:700; margin:0; display:flex; align-items:center; gap:6px;">
          <span>Category:</span>
          <select id="filter_niche_select" style="padding:6px 10px; font-size:12.5px; border-radius:6px; margin:0;" onchange="handleNicheFilter(this.value)">
            <option value="all">All Categories</option>
            <?php foreach ($niches as $n): ?>
              <option value="<?= h(strtolower($n)) ?>"><?= h($n) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <!-- Quick Search Input -->
      <div style="display:flex; align-items:center; gap:8px;">
        <input type="search" id="group_search" class="table-search-input" placeholder="🔍 Search groups..." data-table-filter="groups-table" aria-label="Search groups" style="padding:7px 12px; font-size:12.5px; width:220px; border-radius:6px;">
        <?php if (!empty($targetGroups)): ?>
          <form method="post" action="index.php?page=group-targets-clear-all" data-confirm="Kya aap sabhi groups ko pool se remove karna chahte hain?" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="smallbtn danger" style="padding:7px 10px; font-size:11.5px;">Clear All</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Groups Table -->
    <div class="tablewrap">
      <table id="groups-table">
        <thead>
          <tr>
            <th scope="col" style="min-width:240px;">Group Ka Naam &amp; Link</th>
            <th scope="col" style="min-width:180px;">Juda Hua FB Profile</th>
            <th scope="col">Category</th>
            <th scope="col">Shares Done</th>
            <th scope="col">Status</th>
            <th scope="col" style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($targetGroups as $g): ?>
            <tr data-account-id="<?= (int)($g['account_id'] ?? 0) ?>" data-niche="<?= h(strtolower($g['niche_category'] ?? 'general')) ?>">
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <span style="width:30px; height:30px; border-radius:8px; background:#eff6ff; color:#1877f2; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; flex-shrink:0;">
                    FB
                  </span>
                  <div>
                    <a href="<?= h($g['group_url']) ?>" target="_blank" rel="noopener" style="font-weight:700; font-size:13px; color:var(--ink); text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                      <span><?= h($g['name']) ?></span>
                      <span style="color:#3b82f6; font-size:11px;">↗</span>
                    </a>
                    <div style="font-size:11px; color:var(--muted); word-break:break-all; font-family:monospace; margin-top:2px;">
                      <?= h($g['group_url']) ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <?php if (!empty($g['account_label'])): ?>
                  <span style="display:inline-flex; align-items:center; gap:6px; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:4px 8px; border-radius:6px; font-size:11.5px; font-weight:700;">
                    <span style="color:#1877f2; display:inline-flex;"><?= Icons::platform('facebook', 13) ?></span>
                    <span><?= h($g['account_label']) ?></span>
                  </span>
                <?php else: ?>
                  <span style="font-size:11px; color:var(--muted); background:var(--bg); border:1px dashed var(--line); padding:3px 7px; border-radius:4px;">
                    🌐 General (All Profiles)
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="niche-chip"><?= h($g['niche_category']) ?></span>
              </td>
              <td>
                <span style="font-size:12px; font-weight:700; color:#059669;"><?= (int)$g['total_shares'] ?> shares</span>
              </td>
              <td>
                <form method="post" action="index.php?page=group-target-toggle" style="margin:0;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                  <label class="toggle-switch">
                    <input type="checkbox" name="is_enabled" value="1" <?= (bool)$g['is_enabled'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="toggle-slider"></span>
                  </label>
                </form>
              </td>
              <td style="text-align:right;">
                <div style="display:inline-flex; gap:6px; align-items:center;">
                  <a href="<?= h($g['group_url']) ?>" target="_blank" rel="noopener" class="smallbtn" style="background:#f1f5f9; color:#1e293b; text-decoration:none; padding:4px 8px; font-size:11px; font-weight:600;" title="Facebook par open karein">
                    Visit ↗
                  </a>
                  <form method="post" action="index.php?page=group-target-delete" data-confirm="Remove this group?" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                    <button type="submit" class="smallbtn danger" style="padding:4px 8px; font-size:11px;">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($targetGroups)): ?>
            <tr class="empty-row">
              <td colspan="6" style="text-align:center; padding:48px 16px;">
                <span style="font-size:32px; display:block; margin-bottom:8px;">📁</span>
                <b style="font-size:15px; color:var(--ink); display:block; margin-bottom:4px;">Abhi koi Facebook Group add nahi hai</b>
                <p style="font-size:12.5px; color:var(--muted); max-width:440px; margin:0 auto 16px;">
                  Apne Facebook profile ke sabhi joined groups ko 1-click me import karein ya direct link se add karein.
                </p>
                <div style="display:flex; gap:10px; justify-content:center;">
                  <button type="button" class="btn" style="background:#1877f2; color:#fff;" onclick="document.getElementById('modal-sync-profile-groups').showModal()">
                    📥 Import / Sync Profile Groups
                  </button>
                  <button type="button" class="btn light" onclick="openAddGroupModal('single')">
                    + Add Single Group
                  </button>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ===================================================================== -->
<!-- TAB 2: SCHEDULE POST TO GROUPS                                         -->
<!-- ===================================================================== -->
<div id="tab-pane-schedule" class="groups-main-tab-pane" style="display:none;">

  <!-- ⭐ WSA DIGITAL AGENCY AUTO-PILOT HERO CARD ⭐ -->
  <div class="card" style="max-width:760px; margin:0 auto 24px; border:2px solid #2563eb; background:linear-gradient(135deg, #ffffff 0%, #eff6ff 100%); border-radius:12px; box-shadow:0 4px 14px rgba(37,99,235,0.08); padding:20px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
      <div style="display:flex; align-items:center; gap:12px;">
        <div style="width:46px; height:46px; border-radius:12px; background:#1877f2; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 2px 8px rgba(24,119,242,0.3);">
          <?= Icons::platform('facebook', 24) ?>
        </div>
        <div>
          <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <h2 style="font-size:17px; font-weight:800; margin:0; color:#1e3a8a;">WSA Digital Agency Daily Auto-Pilot</h2>
            <span style="font-size:11px; font-weight:700; background:<?= $autoPilotEnabled ? '#dcfce7; color:#166534;' : '#fef3c7; color:#92400e;' ?> padding:2px 8px; border-radius:20px;">
              <?= $autoPilotEnabled ? '● AUTO-PILOT ON' : '○ PAUSED' ?>
            </span>
          </div>
          <div style="font-size:12.5px; color:#475569; margin-top:2px;">
            Target Page: <b>WSA Digital Agency (Page)</b> &bull; Daily <b><?= (int)$autoPilotCount ?> Groups</b> Rotation
          </div>
        </div>
      </div>

      <div style="display:flex; gap:8px;">
        <button type="button" class="btn light" onclick="document.getElementById('modal-autopilot-settings').showModal()" style="font-size:12px; padding:6px 12px; border-radius:7px;">
          ⚙️ Settings
        </button>
      </div>
    </div>

    <!-- Latest Detected Post/Reel Box -->
    <div style="background:#ffffff; border:1px solid #bfdbfe; border-radius:10px; padding:14px; margin-bottom:16px;">
      <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#1d4ed8; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
        <span>🎬 Latest Live Reel / Post Detected from Page:</span>
      </div>
      <?php if (!empty($cachedLatestPost)): ?>
        <div style="font-size:13px; font-weight:600; color:var(--ink); line-height:1.45; margin-bottom:8px;">
          "<?= h(mb_strimwidth((string)($cachedLatestPost['message'] ?? 'Latest Post from WSA Digital Agency'), 0, 140, '...')) ?>"
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:11.5px; color:var(--muted);">
          <span>📅 <?= !empty($cachedLatestPost['created_time']) ? date('d M Y, h:i A', strtotime($cachedLatestPost['created_time'])) : 'Recent' ?></span>
          <a href="<?= h(MetaGraph::resolvePostUrl($cachedLatestPost, (string)($defaultMainPage['external_id'] ?? ''))) ?>" target="_blank" rel="noopener" style="color:#1877f2; font-weight:700; text-decoration:none;">
            Facebook Par Dekhein ↗
          </a>
        </div>
      <?php else: ?>
        <div style="font-size:12.5px; color:var(--muted);">
          WSA Digital Agency Facebook Page se sync ready hai. Niche button dabate hi latest reel fetch ho jayegi.
        </div>
      <?php endif; ?>
    </div>

    <!-- Status & 1-Click Action -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding-top:4px;">
      <div style="font-size:12px; color:#334155;">
        <?php if ($isTodayQueued): ?>
          <span style="color:#059669; font-weight:700;">✅ Aaj ke <?= (int)$autoPilotCount ?> groups successfully schedule ho chuke hain!</span>
        <?php else: ?>
          <span style="color:#b45309; font-weight:600;">⏳ Aaj ka auto-pilot run nahi hua hai (ya manually trigger karein).</span>
        <?php endif; ?>
        <div style="font-size:11px; color:#64748b; margin-top:2px;">
          Delay: <b><?= (int)$autoPilotInterval ?> Minute</b> har share ke beech &bull; Anti-ban Safe Cadence
        </div>
      </div>

      <div style="display:flex; gap:8px; align-items:center;">
        <form method="post" action="index.php?page=group-autopilot-run-now" style="margin:0;">
          <?= csrf_field() ?>
          <button type="submit" class="btn" style="background:#10b981; color:#ffffff; font-weight:700; font-size:13px; padding:9px 16px; border-radius:8px; box-shadow:0 2px 6px rgba(16,185,129,0.3); display:inline-flex; align-items:center; gap:6px;">
            <span>⚡ Aaj Ke <?= (int)$autoPilotCount ?> Groups Me Abhi Queue Karein</span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- MANUAL SCHEDULE ACCORDION / CARD -->
  <div class="card" style="max-width:760px; margin:0 auto 24px;">
    <div class="cardhead" style="margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:10px;">
      <div>
        <h2 style="font-size:15px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
          <?= Icons::svg('sparkles', 15, 'text-blue') ?>
          <span>Ya Kisi Anya Post Ko Manually Schedule Karein</span>
        </h2>
        <p style="font-size:12px; color:var(--muted); margin:3px 0 0;">
          Agar aap kisi specific post ko manually select karke schedule karna chahte hain:
        </p>
      </div>
    </div>

    <form method="post" action="index.php?page=group-queue-generate">
      <?= csrf_field() ?>

      <label for="queue_post_select" style="font-weight:700; font-size:13px;">1. Kaunsi Post / Reel Share Karni Hai:
        <select id="queue_post_select" name="selected_post_url" style="margin-top:6px; font-weight:600; padding:9px 12px; border-radius:8px;" onchange="onPostSelectChange(this)">
          <optgroup label="🎬 WSA Digital Agency — Live Posts &amp; Reels">
            <?php foreach ($livePagePosts as $lp): ?>
              <?php 
                $pUrl = MetaGraph::resolvePostUrl($lp, (string)($defaultMainPage['external_id'] ?? ''));
                $isReel = (str_contains($pUrl, '/reel/') || str_contains($pUrl, '/watch/') || ($lp['status_type'] ?? '') === 'added_video');
                $dateStr = !empty($lp['created_time']) ? date('d M, h:i A', strtotime($lp['created_time'])) : '';
                $snip = mb_strimwidth(trim($lp['message'] ?? 'Facebook Post'), 0, 70, '...');
                $caption = trim($lp['message'] ?? '');
              ?>
              <option value="<?= h($pUrl) ?>" data-caption="<?= h($caption) ?>">
                <?= $isReel ? '🎬 Reel' : '📄 Post' ?> (<?= h($dateStr) ?>): <?= h($snip) ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
          <?php if (!empty($publishedCampaigns)): ?>
            <optgroup label="📂 Database Campaigns">
              <?php foreach ($publishedCampaigns as $c): ?>
                <option value="campaign_<?= (int)$c['id'] ?>">
                  <?= h($c['name']) ?> (Live Post #<?= h($c['response_id'] ?? 'N/A') ?>)
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          <option value="custom">🔗 Ya Niche Custom Link Daalein</option>
        </select>
      </label>

      <div style="margin-top:12px;">
        <label for="queue_custom_url" style="font-size:12px; font-weight:700; color:var(--ink);">Post / Reel Direct Link:
          <input id="queue_custom_url" name="custom_post_url" placeholder="https://www.facebook.com/... ya custom link" style="margin-top:4px;" value="<?= !empty($livePagePosts[0]) ? h(MetaGraph::resolvePostUrl($livePagePosts[0], (string)($defaultMainPage['external_id'] ?? ''))) : '' ?>">
        </label>
      </div>

      <!-- 2. SENDER PAGES SELECTION (KAUN-KAUN SE PAGES SE SHARE KARNA HAI) -->
      <div style="margin-top:16px; background:#f8fafc; border:1px solid var(--line); border-radius:10px; padding:14px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
          <div>
            <label style="font-weight:700; font-size:13px; color:var(--ink); margin:0;">
              2. Kaun-Kaun Se Pages Se Share Karna Hai (Select Sender Pages):
            </label>
            <div style="font-size:11.5px; color:var(--muted); margin-top:2px;">
              Selected pages aapki post/reel ko open karke groups me share karenge.
            </div>
          </div>
          <button type="button" class="smallbtn" onclick="toggleAllSenderPages(this)" style="font-size:11px; padding:4px 10px; background:#eff6ff; color:#1877f2; border:1px solid #bfdbfe; font-weight:700;">
            Select / Deselect All
          </button>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:8px; max-height:160px; overflow-y:auto; padding:2px;">
          <?php foreach ($boosterPages as $bp): ?>
            <label style="display:flex; align-items:center; gap:8px; background:#ffffff; border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:12px; font-weight:600; cursor:pointer;" title="Page ID: <?= h($bp['external_id']) ?>">
              <input type="checkbox" name="sender_account_ids[]" value="<?= (int)$bp['id'] ?>" class="sender-page-checkbox" checked onchange="updateTotalScheduleCalculation()" style="width:16px; height:16px; cursor:pointer;">
              <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= h($bp['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 3. TARGET GROUP SELECTION: SMART ROTATION vs MANUAL SELECT -->
      <div style="margin-top:16px; background:#f8fafc; border:1px solid var(--line); border-radius:10px; padding:14px;">
        <div style="font-weight:700; font-size:13px; color:var(--ink); margin-bottom:8px;">
          3. Groups Ka Chunaav (Target Groups Selection):
        </div>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
          <!-- Option A: Smart Rotation -->
          <label style="display:flex; align-items:flex-start; gap:10px; background:#ffffff; border:2px solid #1877f2; border-radius:8px; padding:10px 12px; cursor:pointer;" id="label_mode_auto">
            <input type="radio" name="group_selection_mode" value="auto" checked onchange="toggleGroupSelectionMode('auto')" style="margin-top:2px;">
            <div>
              <div style="font-weight:700; font-size:12.5px; color:#1877f2;">🔄 Smart Rotation (Har Baar Alag Group)</div>
              <div style="font-size:11px; color:var(--muted); margin-top:2px; line-height:1.4;">
                Queue me mojood groups ko chhodkar, sabse kam/kabhi na share hue groups me automatically share karega. Har baar naye groups!
              </div>
            </div>
          </label>

          <!-- Option B: Manual Select -->
          <label style="display:flex; align-items:flex-start; gap:10px; background:#ffffff; border:1px solid var(--line); border-radius:8px; padding:10px 12px; cursor:pointer;" id="label_mode_manual">
            <input type="radio" name="group_selection_mode" value="manual" onchange="toggleGroupSelectionMode('manual')" style="margin-top:2px;">
            <div>
              <div style="font-weight:700; font-size:12.5px; color:var(--ink);">🎯 Manual Select (Apni Pasand Ke Groups)</div>
              <div style="font-size:11px; color:var(--muted); margin-top:2px; line-height:1.4;">
                Niche di gayi list se specific groups tick karein jinme aap specifically share karna chahte hain.
              </div>
            </div>
          </label>
        </div>

        <!-- AUTO CONFIG: Niche & Shares Per Page -->
        <div id="container_group_mode_auto" class="twocol" style="gap:12px;">
          <div>
            <label for="queue_shares_per_page" style="font-weight:700; font-size:12px; color:var(--ink);">Har Page Kitne Groups Me Share Kare:
              <select id="queue_shares_per_page" name="shares_per_page" style="margin-top:4px; font-weight:700;" onchange="updateTotalScheduleCalculation()">
                <option value="2">2 Groups Har Page</option>
                <option value="3" selected>3 Groups Har Page (Recommended)</option>
                <option value="4">4 Groups Har Page</option>
                <option value="5">5 Groups Har Page (Full Boost)</option>
              </select>
            </label>
          </div>
          <div>
            <label for="queue_niche" style="font-weight:700; font-size:12px; color:var(--ink);">Group Category (Niche Filter):
              <select id="queue_niche" name="niche_category" style="margin-top:4px;">
                <option value="all">🌐 Sabhi Groups (<?= count($targetGroups) ?> Groups Pool)</option>
                <?php foreach ($niches as $n): ?>
                  <option value="<?= h($n) ?>"><?= h($n) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </div>

        <!-- MANUAL CONFIG: Searchable Checklist -->
        <div id="container_group_mode_manual" style="display:none; margin-top:6px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; gap:8px; flex-wrap:wrap;">
            <input type="search" id="manual_group_search" placeholder="🔍 Search groups by name or niche..." oninput="filterManualGroupList(this.value)" style="flex:1; min-width:200px; padding:6px 10px; font-size:12px; border-radius:6px; border:1px solid var(--line);">
            <div style="display:flex; gap:6px; align-items:center;">
              <button type="button" class="smallbtn" onclick="toggleAllManualGroups(true)" style="font-size:11px; padding:4px 8px;">Select All</button>
              <button type="button" class="smallbtn" onclick="toggleAllManualGroups(false)" style="font-size:11px; padding:4px 8px;">Deselect All</button>
              <span id="manual_group_badge" style="font-size:11.5px; font-weight:700; color:#1877f2; background:#eff6ff; padding:3px 8px; border-radius:6px; border:1px solid #bfdbfe;">
                0 selected
              </span>
            </div>
          </div>

          <div id="manual_groups_scroll_list" style="max-height:220px; overflow-y:auto; border:1px solid var(--line); border-radius:8px; background:#ffffff; padding:4px; display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:6px;">
            <?php foreach ($targetGroups as $tg): ?>
              <?php if (empty($tg['is_enabled'])) continue; ?>
              <label class="manual-group-item" data-group-name="<?= h(strtolower($tg['name'])) ?>" data-group-niche="<?= h(strtolower($tg['niche_category'] ?? '')) ?>" style="display:flex; align-items:flex-start; gap:8px; border:1px solid #f1f5f9; background:#f8fafc; border-radius:6px; padding:6px 8px; font-size:11.5px; cursor:pointer;">
                <input type="checkbox" name="selected_group_ids[]" value="<?= (int)$tg['id'] ?>" class="manual-group-checkbox" onchange="updateTotalScheduleCalculation()" style="margin-top:2px; width:15px; height:15px;">
                <div style="flex:1; min-width:0;">
                  <div style="font-weight:700; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= h($tg['name']) ?>">
                    <?= h($tg['name']) ?>
                  </div>
                  <div style="display:flex; gap:6px; align-items:center; font-size:10.5px; color:var(--muted); margin-top:2px;">
                    <span style="background:#e2e8f0; color:#334155; padding:1px 5px; border-radius:4px; font-weight:600;"><?= h($tg['niche_category']) ?></span>
                    <span><?= (int)$tg['total_shares'] ?> shares</span>
                    <?php if (!empty($tg['last_shared_at'])): ?>
                      <span title="Last shared at <?= h($tg['last_shared_at']) ?>">⏱ <?= date('d M', strtotime($tg['last_shared_at'])) ?></span>
                    <?php else: ?>
                      <span style="color:#059669; font-weight:700;">★ New</span>
                    <?php endif; ?>
                  </div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div id="schedule_calc_summary" style="font-size:12px; color:#1d4ed8; font-weight:700; margin-top:10px; background:#eff6ff; padding:6px 12px; border-radius:6px; border:1px solid #bfdbfe;">
          🔄 Smart Rotation: <?= count($boosterPages) ?> Pages × 3 = <?= count($boosterPages) * 3 ?> Groups (Har run me alag/fresh groups pick honge)
        </div>
      </div>

      <div class="twocol" style="margin-top:14px;">
        <div>
          <label for="queue_interval" style="font-weight:700; font-size:12.5px;">4. Har Share Ke Beech Delay:
            <select id="queue_interval" name="interval_minutes" style="margin-top:4px;">
              <option value="2" selected>⚡ 2 Minute (Fast Testing / Live)</option>
              <option value="3">⚡ 3 Minute (Fast Boost)</option>
              <option value="5">5 Minute</option>
              <option value="15">15 Minute</option>
              <option value="20">20 Minute (Safe &amp; Recommended)</option>
              <option value="30">30 Minute</option>
            </select>
          </label>
        </div>
        <div>
          <label for="queue_caption" style="font-weight:700; font-size:12.5px;">Hook Text (Optional):
            <input id="queue_caption" name="caption" placeholder="e.g. Check this out!" style="margin-top:4px;">
          </label>
        </div>
      </div>

      <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin:16px 0; font-size:12px; color:#1e40af; display:flex; align-items:center; gap:8px;">
        <span>🛡️</span>
        <div><b>Anti-Ban Multi-Page Rotation Active:</b> Har page ko alag-alag groups assign honge aur human-like delay ke sath spread honge.</div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:11px 16px; font-weight:700; font-size:13.5px; background:#1877f2;">
        <?= Icons::svg('sparkles', 15) ?>
        <span>Schedule Multi-Page Group Shares</span>
      </button>
    </form>
  </div>
</div>

<!-- ===================================================================== -->
<!-- TAB 3: SHARING QUEUE & HISTORY                                         -->
<!-- ===================================================================== -->
<div id="tab-pane-queue" class="groups-main-tab-pane" style="display:none;">

  <!-- 🚀 ZERO-CLICK CHROME EXTENSION PROMO / CONTROLLER CARD -->
  <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border:1.5px solid #3b82f6; border-radius:12px; padding:16px 20px; margin-bottom:18px; color:#f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 6px 20px rgba(0,0,0,0.15);">
    <div style="display:flex; align-items:center; gap:14px;">
      <div style="width:46px; height:46px; border-radius:12px; background:#2563eb; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; box-shadow:0 4px 12px rgba(37,99,235,0.4);">
        ⚡
      </div>
      <div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
          <b style="font-size:15px; color:#fff;">Option B: Zero-Click Chrome Extension Ready!</b>
          <span style="background:#10b981; color:#fff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:12px; letter-spacing:0.5px;">BINA BUTTON DABAYE POSTING</span>
        </div>
        <div style="font-size:12.5px; color:#94a3b8; margin-top:3px; line-height:1.4;">
          Extension background me queue check karega, Facebook group kholega, reel card + caption banayega, <b>"Post" button automatically dabayega</b>, aur status (Live / Pending Approval) save karega!
        </div>
      </div>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-extension-setup').showModal()" style="background:#3b82f6; color:#fff; font-weight:700; font-size:12.5px; padding:9px 18px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
        <span>🚀 1-Min Extension Setup Guide</span>
      </button>
    </div>
  </div>

  <div class="card" style="margin-bottom: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px; border-bottom:1px solid var(--line); padding-bottom:10px;">
      <div class="segmented-nav-control">
        <button type="button" id="tab-btn-pending" class="segmented-nav-pill active" onclick="switchQueueTab('pending')">
          <span>⏳ Scheduled Queue</span>
          <span class="pill" style="font-size:10px; padding:1px 6px;"><?= count($pendingQueue) ?></span>
        </button>
        <button type="button" id="tab-btn-published" class="segmented-nav-pill" onclick="switchQueueTab('published')">
          <span>✅ Live Published</span>
          <span class="pill" style="font-size:10px; padding:1px 6px; background:rgba(16,185,129,0.15); color:#059669; font-weight:800;"><?= count($liveSharedQueue) ?></span>
        </button>
        <?php if (!empty($pendingApprovalQueue)): ?>
        <button type="button" id="tab-btn-approval" class="segmented-nav-pill" onclick="switchQueueTab('approval')">
          <span>🟡 Pending Admin Approval</span>
          <span class="pill" style="font-size:10px; padding:1px 6px; background:#fef3c7; color:#92400e; font-weight:800;"><?= count($pendingApprovalQueue) ?></span>
        </button>
        <?php endif; ?>
        <?php if (!empty($failedQueue)): ?>
        <button type="button" id="tab-btn-failed" class="segmented-nav-pill" onclick="switchQueueTab('failed')">
          <span>❌ Failed (Retry)</span>
          <span class="pill" style="font-size:10px; padding:1px 6px; background:#fee2e2; color:#dc2626; font-weight:800;"><?= count($failedQueue) ?></span>
        </button>
        <?php endif; ?>
      </div>
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <form method="post" action="index.php?page=group-queue-process-due" style="margin:0;">
          <?= csrf_field() ?>
          <button type="submit" class="smallbtn" style="background:#10b981; color:#fff; font-weight:700; border:none; padding:6px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" title="Run due scheduled items immediately">
            <span>⚡ Process Due Shares Now</span>
          </button>
        </form>
        <?php if (!empty($pendingQueue)): ?>
          <form method="post" action="index.php?page=group-queue-clear" data-confirm="Cancel all pending shares?" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="smallbtn danger">Clear Pending Queue</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- PANE 1: PENDING -->
    <div id="pane-queue-pending">
      <div class="tablewrap" style="max-height: 520px; overflow-y:auto;">
        <table style="width:100%;">
          <thead>
            <tr>
              <th scope="col" style="width:110px;">Time (IST)</th>
              <th scope="col" style="min-width:180px;">Share As (Page)</th>
              <th scope="col" style="min-width:190px;">Target Facebook Group</th>
              <th scope="col" style="min-width:170px;">Post to Share</th>
              <th scope="col" style="width:95px;">Status</th>
              <th scope="col" style="width:240px; text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingQueue as $q): ?>
              <?php 
                $senderName = !empty($q['sender_account_label']) ? $q['sender_account_label'] : (!empty($q['sender_name']) ? $q['sender_name'] : 'WSA Digital Agency');
                $isDue = (strtotime($q['scheduled_for']) <= time());
              ?>
              <tr>
                <td>
                  <b style="font-size:12px; color:var(--ink);"><?= date('H:i', strtotime($q['scheduled_for'])) ?></b>
                  <div style="font-size:10px; color:var(--muted);"><?= date('d M Y', strtotime($q['scheduled_for'])) ?> (IST)</div>
                </td>
                <td>
                  <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:30px; height:30px; border-radius:8px; background:#eff6ff; color:#1877f2; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                      <?= Icons::platform('facebook', 16) ?>
                    </span>
                    <div style="min-width:0;">
                      <div style="font-weight:800; font-size:12.5px; color:#1e40af; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= h($senderName) ?>">
                        <?= h($senderName) ?>
                      </div>
                      <div style="font-size:10px; color:#059669; font-weight:700; margin-top:2px;">
                        👉 Is Page Se Share Karein
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <a href="<?= h($q['group_url']) ?>" target="_blank" rel="noopener" style="font-weight:700; font-size:12.5px; color:var(--ink); text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                    <span><?= h($q['group_name'] ?? 'Facebook Group') ?></span>
                    <span style="color:#1877f2; font-size:11px;">↗</span>
                  </a>
                  <?php if (!empty($q['niche_category'])): ?>
                    <div style="font-size:10.5px; color:var(--muted);"><?= h($q['niche_category']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?= h($q['post_url']) ?>" target="_blank" rel="noopener" style="font-size:12px; font-weight:700; color:#1877f2; text-decoration:none; display:inline-flex; align-items:center; gap:4px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <span><?= str_contains($q['post_url'], '/reel/') ? '🎬 View Reel' : '📄 View Post' ?> ↗</span>
                  </a>
                  <?php if (!empty($q['caption'])): ?>
                    <div style="font-size:11px; color:var(--muted); max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= h($q['caption']) ?>">
                      <?= h(mb_strimwidth($q['caption'], 0, 38, '...')) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isDue): ?>
                    <span style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-weight:700; font-size:10.5px; padding:2px 7px; border-radius:12px; display:inline-flex; align-items:center; gap:3px;">
                      <span>⚡ Due Now</span>
                    </span>
                  <?php else: ?>
                    <span class="badge-queued" style="font-size:10.5px; padding:2px 7px;">Queued</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <button type="button" class="btn" style="background:#1877f2; color:#fff; font-size:11px; font-weight:700; padding:6px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;" onclick="openGroupAndShareManual(<?= (int)$q['id'] ?>, '<?= h(addslashes($q['group_url'])) ?>', '<?= h(addslashes($q['post_url'])) ?>', '<?= h(addslashes($senderName)) ?>', '<?= h(addslashes($q['group_name'] ?? 'Group')) ?>')">
                    <span>🚀 Share (Manual)</span>
                  </button>
                  <button type="button" class="btn" style="background:#10b981; color:#fff; font-size:11px; font-weight:700; padding:6px 10px; border-radius:6px; margin-left:4px; display:inline-flex; align-items:center; gap:4px;" onclick="openRecordShareModal(<?= (int)$q['id'] ?>, '<?= h(addslashes($q['group_name'] ?? 'Group')) ?>', '<?= h(addslashes($q['group_url'])) ?>', '<?= h(addslashes($senderName)) ?>', '<?= h(addslashes($q['post_url'] ?? '')) ?>', 'shared')">
                    <span>✓ Done</span>
                  </button>
                  <form method="post" action="index.php?page=group-item-delete" style="display:inline-block; margin-left:4px;" data-confirm="Remove this scheduled item?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">
                    <button type="submit" class="smallbtn danger" style="padding:6px 8px; font-size:11px;" title="Delete">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($pendingQueue)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:32px; color:var(--muted); font-size:12.5px;">
                  Abhi koi pending share queue me nahi hai. Upar "Schedule Post" tab se share karein.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PANE 2: LIVE PUBLISHED -->
    <div id="pane-queue-published" style="display:none;">
      <div class="tablewrap" style="max-height: 520px; overflow-y:auto;">
        <table style="width:100%;">
          <thead>
            <tr>
              <th scope="col" style="width:115px;">Shared At</th>
              <th scope="col" style="min-width:160px;">Shared As (Page)</th>
              <th scope="col" style="min-width:180px;">Target Facebook Group</th>
              <th scope="col" style="min-width:190px;">Original Post (Reel)</th>
              <th scope="col" style="min-width:210px;">Live Group Post Link</th>
              <th scope="col" style="width:110px; text-align:right;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($liveSharedQueue as $q): ?>
              <?php 
                $senderName = !empty($q['sender_account_label']) ? $q['sender_account_label'] : (!empty($q['sender_name']) ? $q['sender_name'] : 'WSA Digital Agency');
                $sharedUrl = trim((string)($q['shared_post_url'] ?? ''));
                $isDirectGroupPost = ($sharedUrl !== '' && (str_contains($sharedUrl, '/posts/') || str_contains($sharedUrl, '/permalink/') || str_contains($sharedUrl, '/reel/')));
              ?>
              <tr>
                <td>
                  <b style="font-size:12px; color:var(--ink);"><?= !empty($q['shared_at']) ? date('H:i, d M', strtotime($q['shared_at'])) : 'Completed' ?></b>
                  <div style="font-size:10px; color:var(--muted);"><?= !empty($q['shared_at']) ? date('Y', strtotime($q['shared_at'])) : '' ?></div>
                </td>
                <td>
                  <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:28px; height:28px; border-radius:6px; background:#eff6ff; color:#1877f2; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                      <?= Icons::platform('facebook', 14) ?>
                    </span>
                    <div style="min-width:0;">
                      <b style="font-size:12.5px; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block;" title="<?= h($senderName) ?>">
                        <?= h($senderName) ?>
                      </b>
                      <span style="font-size:10px; color:var(--muted);">Shared via Page</span>
                    </div>
                  </div>
                </td>
                <td>
                  <div style="font-weight:700; font-size:12.5px; color:var(--ink);">
                    <?= h($q['group_name'] ?? 'Facebook Group') ?>
                  </div>
                  <a href="<?= h($q['group_url']) ?>" target="_blank" rel="noopener" style="font-size:11px; color:#1877f2; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:3px; margin-top:2px;">
                    <span>👥 Open Group</span>
                    <span>↗</span>
                  </a>
                </td>
                <td>
                  <a href="<?= h($q['post_url']) ?>" target="_blank" rel="noopener" style="font-size:12px; font-weight:700; color:#1877f2; text-decoration:none; display:inline-flex; align-items:center; gap:4px; max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <span><?= str_contains($q['post_url'], '/reel/') ? '🎬 View Original Reel' : '📄 View Original Post' ?> ↗</span>
                  </a>
                  <?php if (!empty($q['caption'])): ?>
                    <div style="font-size:11px; color:var(--muted); max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px;" title="<?= h($q['caption']) ?>">
                      <?= h(mb_strimwidth($q['caption'], 0, 40, '...')) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isDirectGroupPost): ?>
                    <a href="<?= h($sharedUrl) ?>" target="_blank" rel="noopener" class="btn" style="background:#1877f2; color:#fff; font-size:11px; font-weight:700; padding:5px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
                      <span>🔗 Open Live Group Post ↗</span>
                    </a>
                    <div style="font-size:10px; color:var(--muted); margin-top:3px; max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                      <?= h($sharedUrl) ?>
                    </div>
                  <?php else: ?>
                    <div style="font-size:11.5px; font-weight:700; color:#059669; display:flex; align-items:center; gap:4px;">
                      <span>✅ Live on Group Feed</span>
                    </div>
                    <div style="margin-top:2px;">
                      <a href="<?= h($q['group_url']) ?>" target="_blank" rel="noopener" style="font-size:11px; color:#1877f2; font-weight:700; text-decoration:none;">
                        📁 View in Group Feed ↗
                      </a>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <span class="badge-success" style="display:inline-block; margin-bottom:4px;">Live Published ✓</span>
                  <div>
                    <button type="button" class="smallbtn light" style="font-size:10px; padding:2px 7px;" title="Status change karein ya live post link update karein" onclick="openRecordShareModal(<?= (int)$q['id'] ?>, '<?= h(addslashes($q['group_name'] ?? 'Group')) ?>', '<?= h(addslashes($q['group_url'])) ?>', '<?= h(addslashes($senderName)) ?>', '<?= h(addslashes($q['post_url'] ?? '')) ?>', 'shared')">
                      ✏️ Edit Status
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($liveSharedQueue)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:32px; color:var(--muted); font-size:12.5px;">
                  Abhi koi Live Published post record nahi hai.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PANE 2B: PENDING ADMIN APPROVAL -->
    <div id="pane-queue-approval" style="display:none;">
      <div class="tablewrap" style="max-height: 520px; overflow-y:auto;">
        <table style="width:100%;">
          <thead>
            <tr>
              <th scope="col" style="width:115px;">Submitted (IST)</th>
              <th scope="col" style="min-width:160px;">Submitted As</th>
              <th scope="col" style="min-width:180px;">Target Facebook Group</th>
              <th scope="col" style="min-width:190px;">Original Post (Reel)</th>
              <th scope="col" style="min-width:210px;">Approval Status</th>
              <th scope="col" style="width:160px; text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingApprovalQueue as $q): ?>
              <?php 
                $senderName = !empty($q['sender_account_label']) ? $q['sender_account_label'] : (!empty($q['sender_name']) ? $q['sender_name'] : 'WSA Digital Agency');
              ?>
              <tr>
                <td>
                  <b style="font-size:12px; color:#b45309;"><?= !empty($q['shared_at']) ? date('H:i, d M', strtotime($q['shared_at'])) : date('H:i, d M', strtotime($q['scheduled_for'])) ?></b>
                  <div style="font-size:10px; color:var(--muted);"><?= date('Y', strtotime($q['scheduled_for'])) ?></div>
                </td>
                <td>
                  <b style="font-size:12px; color:var(--ink);"><?= h($senderName) ?></b>
                </td>
                <td>
                  <div style="font-weight:700; font-size:12.5px; color:var(--ink);">
                    <?= h($q['group_name'] ?? 'Facebook Group') ?>
                  </div>
                  <a href="<?= h($q['group_url']) ?>" target="_blank" rel="noopener" style="font-size:11px; color:#1877f2; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:3px; margin-top:2px;">
                    <span>👥 Open Group ↗</span>
                  </a>
                </td>
                <td>
                  <a href="<?= h($q['post_url']) ?>" target="_blank" rel="noopener" style="font-size:12px; font-weight:700; color:#1877f2; text-decoration:none; display:inline-flex; align-items:center; gap:4px; max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <span><?= str_contains($q['post_url'], '/reel/') ? '🎬 View Reel' : '📄 View Post' ?> ↗</span>
                  </a>
                  <?php if (!empty($q['caption'])): ?>
                    <div style="font-size:11px; color:var(--muted); max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px;" title="<?= h($q['caption']) ?>">
                      <?= h(mb_strimwidth($q['caption'], 0, 40, '...')) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="background:#fffbeb; border:1px solid #fef3c7; padding:6px 10px; border-radius:6px;">
                    <span style="font-size:11.5px; font-weight:700; color:#b45309; display:flex; align-items:center; gap:5px;">
                      <span>🟡 Waiting for Admin Approval</span>
                    </span>
                    <div style="font-size:10.5px; color:#92400e; margin-top:2px;">
                      Admin approve karne par Live link add karein.
                    </div>
                  </div>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <button type="button" class="btn" style="background:#10b981; color:#fff; font-size:11px; font-weight:700; padding:6px 10px; border-radius:6px; cursor:pointer;" onclick="openRecordShareModal(<?= (int)$q['id'] ?>, '<?= h(addslashes($q['group_name'] ?? 'Group')) ?>', '<?= h(addslashes($q['group_url'])) ?>', '<?= h(addslashes($senderName)) ?>', '<?= h(addslashes($q['post_url'] ?? '')) ?>', 'shared')">
                    ✓ Mark Approved & Add Link
                  </button>
                  <form method="post" action="index.php?page=group-item-delete" style="display:inline-block; margin-left:4px;" data-confirm="Remove this pending approval item?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">
                    <button type="submit" class="smallbtn danger" style="padding:6px 8px; font-size:11px;" title="Delete">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($pendingApprovalQueue)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:32px; color:var(--muted); font-size:12.5px;">
                  Koi post Admin Approval me pending nahi hai.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PANE 3: FAILED (RETRY) -->
    <div id="pane-queue-failed" style="display:none;">
      <div class="tablewrap" style="max-height: 520px; overflow-y:auto;">
        <table style="width:100%;">
          <thead>
            <tr>
              <th scope="col" style="width:110px;">Time (IST)</th>
              <th scope="col" style="min-width:170px;">Share As</th>
              <th scope="col" style="min-width:180px;">Target Group</th>
              <th scope="col" style="min-width:200px;">Error Details</th>
              <th scope="col" style="width:170px; text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($failedQueue as $q): ?>
              <?php 
                $senderName = !empty($q['sender_account_label']) ? $q['sender_account_label'] : (!empty($q['sender_name']) ? $q['sender_name'] : 'WSA Digital Agency');
              ?>
              <tr>
                <td>
                  <b style="font-size:12px; color:#dc2626;"><?= date('H:i', strtotime($q['scheduled_for'])) ?></b>
                  <div style="font-size:10px; color:var(--muted);"><?= date('d M Y', strtotime($q['scheduled_for'])) ?></div>
                </td>
                <td>
                  <span style="font-weight:700; font-size:12px; color:var(--ink);"><?= h($senderName) ?></span>
                </td>
                <td>
                  <a href="<?= h($q['group_url']) ?>" target="_blank" rel="noopener" style="font-weight:700; font-size:12px; color:var(--ink); text-decoration:none;">
                    <?= h($q['group_name'] ?? 'Facebook Group') ?> ↗
                  </a>
                </td>
                <td>
                  <span style="color:#dc2626; font-size:11.5px; font-weight:600; display:block;">
                    <?= h($q['error_message'] ?: 'Post button remained disabled or timed out') ?>
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <form method="post" action="index.php?page=group-item-requeue" style="display:inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">
                    <button type="submit" class="btn" style="background:#3b82f6; color:#fff; font-size:11px; font-weight:700; padding:6px 10px; border-radius:6px; cursor:pointer;" title="Re-queue for Auto-Pilot to try again">
                      🔄 Retry
                    </button>
                  </form>
                  <form method="post" action="index.php?page=group-item-delete" style="display:inline-block; margin-left:4px;" data-confirm="Remove this failed item?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">
                    <button type="submit" class="smallbtn danger" style="padding:6px 8px; font-size:11px;" title="Delete">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($failedQueue)): ?>
              <tr>
                <td colspan="5" style="text-align:center; padding:32px; color:var(--muted); font-size:12.5px;">
                  Koi failed items nahi hain! Sabhi tasks smooth hain.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===================================================================== -->
<!-- TAB 4: SENDER PROFILES                                                 -->
<!-- ===================================================================== -->
<div id="tab-pane-profiles" class="groups-main-tab-pane" style="display:none;">
  <div class="card" style="margin-bottom: 24px;">
    <div class="cardhead" style="margin-bottom: 14px;">
      <div>
        <h2 style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700; margin:0;">
          <?= Icons::svg('user', 18) ?> <span>Multi-Account Rotation Profiles (<?= count($senderProfiles) ?>)</span>
        </h2>
        <p style="font-size:12px; color:var(--muted); margin:3px 0 0;">
          Agar aapke paas multiple personal Facebook profiles hain jinse aap groups me share karna chahte hain, toh yahan add karein.
        </p>
      </div>
      <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="document.getElementById('modal-add-profile').showModal()">
        + Add Profile
      </button>
    </div>

    <?php if (!empty($senderProfiles)): ?>
      <div class="grid three" style="gap: 12px;">
        <?php foreach ($senderProfiles as $sp): ?>
          <?php $isEnabled = (bool)$sp['is_enabled']; ?>
          <div style="border:1px solid var(--line); border-radius:10px; padding:12px; background:var(--bg); display:flex; justify-content:space-between; align-items:center;">
            <div>
              <b style="font-size:13px; color:var(--ink); display:block;"><?= h($sp['name']) ?></b>
              <small style="font-size:11px; color:var(--muted);"><?= (int)$sp['daily_limit'] ?> shares/day</small>
            </div>
            <div style="display:flex; gap:6px; align-items:center;">
              <form method="post" action="index.php?page=sender-profile-toggle" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="profile_id" value="<?= (int)$sp['id'] ?>">
                <label class="toggle-switch">
                  <input type="checkbox" name="is_enabled" value="1" <?= $isEnabled ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span class="toggle-slider"></span>
                </label>
              </form>
              <form method="post" action="index.php?page=sender-profile-delete" data-confirm="Remove profile?" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="profile_id" value="<?= (int)$sp['id'] ?>">
                <button type="submit" class="smallbtn danger" style="padding:2px 6px; font-size:10px;">✕</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="font-size:12px; color:var(--muted); margin:0;">Abhi koi separate sender profile add nahi hai. Default connected accounts se sharing hoti hai.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL 1: SYNC / IMPORT GROUPS (SUPER SIMPLE)                           -->
<!-- ===================================================================== -->
<dialog id="modal-sync-profile-groups" style="border:1px solid var(--line); border-radius:12px; padding:22px; max-width:580px; width:92%; background:var(--bg); color:var(--ink); box-shadow:0 12px 32px rgba(0,0,0,0.25);">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--line);">
    <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
      <span style="color:#1877f2;"><?= Icons::platform('facebook', 18) ?></span>
      <span>Facebook Groups Import Karein</span>
    </h3>
    <button type="button" style="background:none; border:none; font-size:18px; color:var(--muted); cursor:pointer;" onclick="document.getElementById('modal-sync-profile-groups').close()">✕</button>
  </div>

  <form method="post" action="index.php?page=sync-fb-profile-groups" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="sync_mode" value="paste">

    <label for="sync_account_id" style="font-size:12.5px; font-weight:700;">Yeh Groups Kis Facebook Profile Ke Hain?:
      <select id="sync_account_id" name="account_id" style="margin-top:4px;">
        <option value="">🌐 General Pool / All Profiles</option>
        <?php foreach ($connectedFbAccounts as $fbAcc): ?>
          <option value="<?= (int)$fbAcc['id'] ?>">
            <?= h($fbAcc['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <!-- Method A: Save Page File Upload (Simplest) -->
    <div style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:10px; padding:16px; margin:14px 0; text-align:center;">
      <span style="font-size:24px; display:block; margin-bottom:4px;">📁</span>
      <b style="font-size:13px; color:var(--ink); display:block;">Tareeqa 1: Saved Facebook Page File Upload Karein</b>
      <p style="font-size:11.5px; color:var(--muted); margin:4px 0 10px;">
        Apne Facebook <a href="https://www.facebook.com/groups/joins/" target="_blank" rel="noopener" style="color:#2563eb; font-weight:700;">groups/joins</a> page par <b>Ctrl + S</b> daba kar page save karein, fir wo file yahan select karein:
      </p>
      <input type="file" id="html_file" name="html_file" accept=".html,.htm,.txt" style="font-size:12px;">
    </div>

    <div style="text-align:center; font-size:11px; font-weight:800; color:var(--muted); margin:10px 0; letter-spacing:0.04em;">
      — YA NICHE DIRECT LINKS PASTE KAREIN —
    </div>

    <!-- Method B: Direct Paste -->
    <label for="raw_groups_data" style="font-size:12.5px; font-weight:700;">Tareeqa 2: Group Links / Text Paste:
      <textarea id="raw_groups_data" name="raw_groups_data" rows="4" placeholder="https://www.facebook.com/groups/digitalmarketingindia&#10;https://www.facebook.com/groups/109283748291&#10;Ya facebook.com/groups/joins se Ctrl+A karke paste karein..." style="width:100%; box-sizing:border-box; font-family:monospace; font-size:12px; margin-top:4px; padding:8px;"></textarea>
    </label>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
      <button type="button" class="smallbtn" style="background:#f1f5f9; color:#475569; font-size:11.5px;" onclick="copySyncScript(this)">
        📋 Copy Auto-Script
      </button>
      <div style="display:flex; gap:8px;">
        <button type="button" class="btn light" onclick="document.getElementById('modal-sync-profile-groups').close()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#1877f2;">Import Groups 🚀</button>
      </div>
    </div>
  </form>
</dialog>

<!-- ===================================================================== -->
<!-- MODAL 2: ADD SINGLE / BULK GROUP                                       -->
<!-- ===================================================================== -->
<dialog id="modal-add-group" style="border:1px solid var(--line); border-radius:12px; padding:22px; max-width:520px; width:92%; background:var(--bg); color:var(--ink); box-shadow:0 12px 32px rgba(0,0,0,0.2);">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--line);">
    <div style="display:flex; gap:8px;">
      <button type="button" id="tab-btn-single" class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="switchGroupTab('single')">
        Single Group
      </button>
      <button type="button" id="tab-btn-bulk" class="btn light" style="padding:6px 12px; font-size:12px;" onclick="switchGroupTab('bulk')">
        Bulk CSV Upload
      </button>
    </div>
    <button type="button" style="background:none; border:none; font-size:18px; color:var(--muted); cursor:pointer;" onclick="document.getElementById('modal-add-group').close()">✕</button>
  </div>

  <!-- Single Form -->
  <form id="form-single-group" method="post" action="index.php?page=group-target-add">
    <?= csrf_field() ?>
    <label for="new_grp_account">Facebook Profile / Account:
      <select id="new_grp_account" name="account_id" style="margin-top:4px;">
        <option value="">🌐 General Pool / All Profiles</option>
        <?php foreach ($connectedFbAccounts as $fbAcc): ?>
          <option value="<?= (int)$fbAcc['id'] ?>">
            <?= h($fbAcc['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label for="new_grp_name" style="margin-top:8px;">Group Ka Naam:
      <input id="new_grp_name" name="name" required placeholder="e.g. Digital Marketing India Hub" style="margin-top:4px;">
    </label>
    <label for="new_grp_url" style="margin-top:8px;">Group Link / URL:
      <input id="new_grp_url" name="group_url" required placeholder="https://www.facebook.com/groups/123456789" style="margin-top:4px;">
    </label>
    <div class="twocol" style="margin-top:8px;">
      <label for="new_grp_niche">Category:
        <input id="new_grp_niche" name="niche_category" value="Digital Marketing" style="margin-top:4px;">
      </label>
      <label for="new_grp_members">Members (Approx):
        <input id="new_grp_members" name="member_count" placeholder="e.g. 50K" style="margin-top:4px;">
      </label>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
      <button type="button" class="btn light" onclick="document.getElementById('modal-add-group').close()">Cancel</button>
      <button type="submit" class="btn btn-primary">Add Group</button>
    </div>
  </form>

  <!-- Bulk Form -->
  <form id="form-bulk-groups" method="post" action="index.php?page=group-target-bulk-add" enctype="multipart/form-data" style="display:none;">
    <?= csrf_field() ?>
    <label for="bulk_grp_account">Facebook Profile:
      <select id="bulk_grp_account" name="account_id" style="margin-top:4px;">
        <option value="">🌐 General Pool / All Profiles</option>
        <?php foreach ($connectedFbAccounts as $fbAcc): ?>
          <option value="<?= (int)$fbAcc['id'] ?>"><?= h($fbAcc['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div style="background:var(--bg); border:2px dashed var(--line); border-radius:8px; padding:12px; text-align:center; margin:12px 0;">
      <label for="csv_file_input" style="cursor:pointer; display:block; margin:0;">
        <span style="font-size:20px; display:block;">📄</span>
        <b style="font-size:12px;">Upload CSV Sheet (.csv)</b>
        <input id="csv_file_input" type="file" name="csv_file" accept=".csv,text/csv" style="margin-top:6px; font-size:12px;">
      </label>
    </div>

    <label for="bulk_groups_input">Ya Direct Links Paste Karein (Har line me ek):
      <textarea id="bulk_groups_input" name="bulk_groups" rows="3" placeholder="https://www.facebook.com/groups/...&#10;https://www.facebook.com/groups/..." style="width:100%; box-sizing:border-box; font-family:monospace; font-size:12px; margin-top:4px; padding:8px;"></textarea>
    </label>

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
      <button type="button" class="btn light" onclick="document.getElementById('modal-add-group').close()">Cancel</button>
      <button type="submit" class="btn btn-primary" style="background:#059669;">Upload &amp; Save</button>
    </div>
  </form>
</dialog>

<!-- MODAL 3: ADD SENDER PROFILE -->
<dialog id="modal-add-profile" style="border:1px solid var(--line); border-radius:12px; padding:22px; max-width:480px; width:90%; background:var(--bg); color:var(--ink); box-shadow:0 12px 32px rgba(0,0,0,0.2);">
  <form method="post" action="index.php?page=sender-profile-add">
    <?= csrf_field() ?>
    <h3 style="margin:0 0 10px; font-size:15px; font-weight:700;">Add Sender Profile</h3>
    <label for="prof_name">Profile Name:
      <input id="prof_name" name="name" required placeholder="e.g. Rahul Sharma (Personal Profile)" style="margin-top:4px;">
    </label>
    <label for="prof_url" style="margin-top:8px;">Profile URL (Optional):
      <input id="prof_url" name="profile_url" placeholder="https://www.facebook.com/username" style="margin-top:4px;">
    </label>
    <label for="prof_limit" style="margin-top:8px;">Daily Share Limit:
      <input id="prof_limit" name="daily_limit" type="number" value="20" min="5" max="50" style="margin-top:4px;">
    </label>
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
      <button type="button" class="btn light" onclick="document.getElementById('modal-add-profile').close()">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Profile</button>
    </div>
  </form>
</dialog>

<!-- MODAL 4: MARK SHARED -->
<dialog id="modal-record-share" style="border:1px solid var(--line); border-radius:14px; padding:22px; max-width:540px; width:92%; background:var(--bg); color:var(--ink); box-shadow:0 15px 35px rgba(0,0,0,0.25);">
  <form method="post" action="index.php?page=group-item-mark-shared">
    <?= csrf_field() ?>
    <input type="hidden" id="record_queue_id" name="queue_id" value="">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; border-bottom:1px solid var(--line); padding-bottom:8px;">
      <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:6px;">
        <span style="color:#10b981;">📋</span>
        <span>Record Group Share Details</span>
      </h3>
      <button type="button" class="smallbtn" onclick="document.getElementById('modal-record-share').close()" style="background:transparent; border:none; font-size:16px; cursor:pointer;">✕</button>
    </div>

    <!-- Assigned Details Info Box -->
    <div style="background:#f8fafc; border:1px solid var(--line); border-radius:8px; padding:12px; margin-bottom:14px; font-size:12.5px;">
      <div style="margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--muted); font-weight:600;">Share As (Page):</span>
        <b id="record_sender_name" style="color:#1d4ed8; font-weight:800;"></b>
      </div>
      <div style="margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--muted); font-weight:600;">Target Group:</span>
        <b id="record_group_name" style="color:var(--ink); font-weight:800;"></b>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--muted); font-weight:600;">Group Page Link:</span>
        <a id="record_group_link" href="#" target="_blank" rel="noopener" style="color:#1877f2; font-weight:700; text-decoration:none;">Open Group ↗</a>
      </div>
    </div>

    <!-- Status Selection: Live vs Pending Approval -->
    <div style="margin-bottom:14px;">
      <label style="font-weight:700; font-size:12.5px; display:block; margin-bottom:6px;">
        Post Status (Choose Current State):
      </label>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <label style="flex:1; min-width:180px; display:flex; align-items:center; gap:8px; background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:10px 12px; cursor:pointer; font-weight:700; font-size:12px; color:#166534;">
          <input type="radio" id="status_radio_shared" name="share_status" value="shared" checked style="width:16px; height:16px; accent-color:#10b981;">
          <span>✅ Live Published<br><small style="font-weight:normal; color:#15803d; font-size:11px;">Group me post direct live ho gayi</small></span>
        </label>
        <label style="flex:1; min-width:180px; display:flex; align-items:center; gap:8px; background:#fefce8; border:1.5px solid #fde047; border-radius:8px; padding:10px 12px; cursor:pointer; font-weight:700; font-size:12px; color:#854d0e;">
          <input type="radio" id="status_radio_pending" name="share_status" value="pending_approval" style="width:16px; height:16px; accent-color:#eab308;">
          <span>🟡 Pending Approval<br><small style="font-weight:normal; color:#a16207; font-size:11px;">Admin approval / review manga hai</small></span>
        </label>
      </div>
    </div>

    <label for="record_shared_url" style="font-weight:700; font-size:12.5px; display:block; margin-bottom:4px;">
      🔗 Group Ke Andar Ka Post Link (Live Group Post URL):
      <input id="record_shared_url" name="shared_post_url" placeholder="https://www.facebook.com/groups/.../posts/... ya permalink" style="margin-top:6px; font-size:12.5px; padding:9px 12px; width:100%;">
    </label>
    <div style="font-size:11.5px; color:var(--muted); margin-top:5px; line-height:1.4;">
      💡 Tip: Facebook group me share karne ke baad, post ke teen dots (...) ya "Share" &rarr; "Copy Link" karke yahan paste karein. Agar Pending Approval hai toh link baad me bhi add kar sakte hain.
    </div>

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px; padding-top:10px; border-top:1px solid var(--line);">
      <button type="button" class="btn light" onclick="document.getElementById('modal-record-share').close()">Cancel</button>
      <button type="submit" class="btn" style="background:#10b981; color:#fff; font-weight:700; padding:8px 16px;">Save &amp; Record ✓</button>
    </div>
  </form>
</dialog>

<!-- MODAL 5: AUTO-PILOT SETTINGS -->
<dialog id="modal-autopilot-settings" style="border:1px solid var(--line); border-radius:14px; padding:24px; max-width:520px; width:92%; background:var(--bg); color:var(--ink); box-shadow:0 15px 35px rgba(0,0,0,0.25);">
  <form method="post" action="index.php?page=group-autopilot-save-settings">
    <?= csrf_field() ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 style="margin:0; font-size:17px; font-weight:800; display:flex; align-items:center; gap:8px;">
        <span>🤖 WSA Digital Agency Auto-Pilot</span>
      </h3>
      <button type="button" class="smallbtn" onclick="document.getElementById('modal-autopilot-settings').close()" style="background:transparent; border:none; font-size:16px;">✕</button>
    </div>

    <div style="margin-bottom:14px;">
      <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; font-size:13.5px; background:var(--bg-muted, #f8fafc); padding:10px 14px; border-radius:8px; border:1px solid var(--line);">
        <input type="checkbox" name="autopilot_enabled" value="1" <?= $autoPilotEnabled ? 'checked' : '' ?> style="width:18px; height:18px;">
        <span>Daily Autonomous Auto-Pilot Enable Rakhein</span>
      </label>
    </div>

    <div style="margin-bottom:12px;">
      <label for="ap_account_id" style="font-weight:700; font-size:12.5px;">Source Facebook Page:
        <select id="ap_account_id" name="account_id" style="margin-top:4px;">
          <?php foreach ($connectedFbAccounts as $acc): ?>
            <option value="<?= (int)$acc['id'] ?>" <?= ((int)$acc['id'] === (int)$autoPilotAccountId) ? 'selected' : '' ?>>
              <?= h($acc['label']) ?> (Page ID: <?= h($acc['external_id']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div style="font-size:11px; color:var(--muted); margin-top:2px;">Is page ki nayi reel/post automatically pick hogi.</div>
    </div>

    <div class="twocol" style="margin-bottom:14px;">
      <div>
        <label for="ap_daily_count" style="font-weight:700; font-size:12.5px;">Daily Groups Limit:
          <input id="ap_daily_count" name="daily_count" type="number" min="1" max="50" value="<?= (int)$autoPilotCount ?>" style="margin-top:4px;" required>
        </label>
        <div style="font-size:11px; color:var(--muted); margin-top:2px;">Har din kitne groups me schedule ho (Default: 5).</div>
      </div>
      <div>
        <label for="ap_interval" style="font-weight:700; font-size:12.5px;">Delay Between Shares:
          <select id="ap_interval" name="interval_mins" style="margin-top:4px;">
            <option value="2" <?= ((int)$autoPilotInterval === 2) ? 'selected' : '' ?>>⚡ 2 Minute (Fast Testing / Live)</option>
            <option value="3" <?= ((int)$autoPilotInterval === 3) ? 'selected' : '' ?>>⚡ 3 Minute (Fast Boost)</option>
            <option value="5" <?= ((int)$autoPilotInterval === 5) ? 'selected' : '' ?>>5 Minute</option>
            <option value="15" <?= ((int)$autoPilotInterval === 15) ? 'selected' : '' ?>>15 Minute</option>
            <option value="20" <?= ((int)$autoPilotInterval === 20) ? 'selected' : '' ?>>20 Minute</option>
            <option value="30" <?= ((int)$autoPilotInterval === 30) ? 'selected' : '' ?>>30 Minute (Recommended)</option>
            <option value="45" <?= ((int)$autoPilotInterval === 45) ? 'selected' : '' ?>>45 Minute</option>
            <option value="60" <?= ((int)$autoPilotInterval === 60) ? 'selected' : '' ?>>60 Minute (1 Hour)</option>
          </select>
        </label>
        <div style="font-size:11px; color:var(--muted); margin-top:2px;">Anti-ban spacing for safety.</div>
      </div>
    </div>
            <option value="15" <?= ((int)$autoPilotInterval === 15) ? 'selected' : '' ?>>15 Minute</option>
            <option value="20" <?= ((int)$autoPilotInterval === 20) ? 'selected' : '' ?>>20 Minute</option>
            <option value="30" <?= ((int)$autoPilotInterval === 30) ? 'selected' : '' ?>>30 Minute (Recommended)</option>
            <option value="45" <?= ((int)$autoPilotInterval === 45) ? 'selected' : '' ?>>45 Minute</option>
            <option value="60" <?= ((int)$autoPilotInterval === 60) ? 'selected' : '' ?>>60 Minute (1 Hour)</option>
          </select>
        </label>
        <div style="font-size:11px; color:var(--muted); margin-top:2px;">Anti-ban spacing for safety.</div>
      </div>
    </div>

    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; font-size:12px; color:#1e40af; margin-bottom:16px;">
      <b>Kaise Kaam Karta Hai?</b>
      <div style="margin-top:4px; line-height:1.4;">
        Cron worker har din page se latest reel/post uthayega aur aapke groups pool me se 5 un-shared groups chun kar schedule kar dega (Round-Robin rotation).
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:8px;">
      <button type="button" class="btn light" onclick="document.getElementById('modal-autopilot-settings').close()">Cancel</button>
      <button type="submit" class="btn btn-primary" style="background:#1877f2; font-weight:700;">Save Auto-Pilot Settings</button>
    </div>
  </form>
</dialog>

<!-- MODAL 6: CHANGE DEFAULT MAIN PAGE -->
<dialog id="modal-change-default-page" style="border:1px solid var(--line); border-radius:14px; padding:22px; max-width:480px; width:90%; background:var(--bg); color:var(--ink); box-shadow:0 15px 35px rgba(0,0,0,0.25);">
  <form method="post" action="index.php?page=set-default-main-page">
    <?= csrf_field() ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:800;">🔄 Default Main Facebook Page Select Karein</h3>
      <button type="button" class="smallbtn" onclick="document.getElementById('modal-change-default-page').close()" style="background:transparent; border:none; font-size:16px;">✕</button>
    </div>
    <p style="font-size:12px; color:var(--muted); margin:0 0 14px;">
      Jis page ko aap default banayenge, uski 7-days ki live posts/reels feed me dikhengi aur auto-pilot bhi usi page ke posts share karega.
    </p>
    <label for="select_main_page_id" style="font-weight:700; font-size:12.5px;">Facebook Page Chunein:
      <select id="select_main_page_id" name="main_page_account_id" style="margin-top:6px;">
        <?php foreach ($connectedFbAccounts as $acc): ?>
          <option value="<?= (int)$acc['id'] ?>" <?= ((int)$acc['id'] === (int)$defaultMainPageId) ? 'selected' : '' ?>>
            <?= h($acc['label']) ?> (ID: <?= h($acc['external_id']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
      <button type="button" class="btn light" onclick="document.getElementById('modal-change-default-page').close()">Cancel</button>
      <button type="submit" class="btn btn-primary" style="background:#1877f2; font-weight:700;">Set As Default Page</button>
    </div>
  </form>
</dialog>

<!-- MODAL 7: LIKE & COMMENT BOOSTER -->
<dialog id="modal-boost-post" style="border:1px solid var(--line); border-radius:14px; padding:22px; max-width:640px; width:94%; background:var(--bg); color:var(--ink); box-shadow:0 15px 35px rgba(0,0,0,0.25);">
  <?php 
    $boosterPages = array_values(array_filter($connectedFbAccounts, fn($a) => (int)$a['id'] !== (int)$defaultMainPageId));
    $defaultCommentsPool = [
      "Outstanding work and strategic execution! Great job WSA! 🚀",
      "WSA Digital Agency always delivers top quality results! 🔥",
      "Highly recommended team for digital marketing and brand scaling! ⭐⭐⭐⭐⭐",
      "Brilliant insights and creativity as always! 👏",
      "Consistent quality and dedicated team. Strongly recommend!",
      "Top digital marketing agency with proven results! 👍",
      "Awesome work, always exceeding expectations! 💯",
      "Superb expertise and dedication by WSA Digital Agency!",
      "Great business advice! Really helpful for entrepreneurs 📈",
      "Love the data-driven approach by WSA! Keep it up 🙌",
      "Best marketing team to scale online presence! 🎯",
      "Amazing content as always! Very inspiring work ⭐"
    ];
  ?>
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
      <span>💬 Like &amp; Comment Station (<?= count($boosterPages) ?> Pages)</span>
    </h3>
    <button type="button" class="smallbtn" onclick="document.getElementById('modal-boost-post').close()" style="background:transparent; border:none; font-size:16px;">✕</button>
  </div>

  <div style="background:#f8fafc; border:1px solid var(--line); border-radius:8px; padding:12px; margin-bottom:14px;">
    <div style="font-size:11px; font-weight:700; color:#1877f2; text-transform:uppercase;">Target Reel / Post:</div>
    <div id="boost_modal_snippet" style="font-size:12.5px; color:var(--ink); margin-top:4px; font-weight:600; line-height:1.4;"></div>
    <div style="margin-top:6px;">
      <a id="boost_modal_link" href="#" target="_blank" rel="noopener" style="font-size:11.5px; color:#1877f2; font-weight:700; text-decoration:none;">Open Live Reel on Facebook ↗</a>
    </div>
  </div>

  <!-- Multi-Page Comment & Like Grid -->
  <div style="margin-bottom:16px;">
    <div style="font-size:12px; font-weight:700; color:var(--ink); margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
      <span>👥 Aapke Connected Pages Ke Liye Unique Comments:</span>
      <span style="font-size:11px; color:var(--muted); font-weight:normal;"><?= count($boosterPages) ?> Pages Available</span>
    </div>

    <div style="display:flex; flex-direction:column; gap:8px; max-height:220px; overflow-y:auto; padding-right:4px;">
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

  <!-- API Dispatch Option -->
  <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
      <div style="font-size:12px; font-weight:700; color:#166534;">🚀 1-Click Automated API Boost:</div>
      <div style="font-size:11px; color:#15803d; margin-top:2px;">
        Sabhi <?= count($boosterPages) ?> pages se automatically backend se Like aur Comment request send karein.
      </div>
    </div>
    <form method="post" action="index.php?page=group-post-boost" style="margin:0;">
      <?= csrf_field() ?>
      <input type="hidden" id="boost_input_post_id" name="target_post_id" value="">
      <input type="hidden" id="boost_input_post_url" name="target_post_url" value="">
      <button type="submit" class="btn" style="background:#10b981; color:#fff; font-weight:700; font-size:12px; padding:7px 14px; border-radius:6px;">
        ⚡ Dispatch API Boost Now
      </button>
    </form>
  </div>

  <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid var(--line); font-size:11px; color:var(--muted);">
    <div>💡 Tip: Facebook par top-right se page switch karke 2 second me drop kar sakte hain.</div>
    <button type="button" class="btn light" onclick="document.getElementById('modal-boost-post').close()" style="font-size:12px; padding:5px 12px;">Close</button>
  </div>
</dialog>

<!-- MODAL 8: CHROME EXTENSION SETUP GUIDE -->
<dialog id="modal-extension-setup" style="border:1px solid var(--line); border-radius:14px; padding:24px; max-width:620px; width:92%; background:var(--bg); color:var(--ink); box-shadow:0 20px 45px rgba(0,0,0,0.3);">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:12px;">
    <div>
      <h3 style="margin:0; font-size:18px; font-weight:800; display:flex; align-items:center; gap:8px;">
        <span style="color:#2563eb;">🚀</span>
        <span>WSA Chrome Extension Setup (Zero-Click Auto Share)</span>
      </h3>
      <p style="margin:4px 0 0; font-size:12.5px; color:var(--muted);">
        Aapko koi bhi button dabane ki zaroorat nahi padegi — extension sabkuch autonomously share karega!
      </p>
    </div>
    <button type="button" class="smallbtn" onclick="document.getElementById('modal-extension-setup').close()" style="background:transparent; border:none; font-size:18px; cursor:pointer;">✕</button>
  </div>

  <div style="background:#f1f5f9; border:1.5px dashed #94a3b8; border-radius:10px; padding:14px 16px; margin-bottom:18px;">
    <div style="font-size:11px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Extension Folder Location (Path):</div>
    <div style="display:flex; gap:10px; align-items:center; margin-top:6px;">
      <input type="text" id="ext-folder-path" readonly value="c:\xampp\htdocs\wsa-social\extension" style="flex:1; font-family:monospace; font-size:13px; font-weight:700; background:#fff; border:1px solid #cbd5e1; padding:8px 12px; border-radius:6px; color:#0f172a;">
      <button type="button" id="btn-copy-ext-path" onclick="copyExtensionFolder(this)" class="btn" style="background:#2563eb; color:#fff; font-weight:700; font-size:12px; padding:8px 16px; border-radius:6px; white-space:nowrap;">
        📋 Copy Path
      </button>
    </div>
  </div>

  <div style="font-weight:800; font-size:13px; margin-bottom:10px; color:var(--ink);">
    Chrome me Load Karne Ke 4 Aasan Steps (1 Minute):
  </div>

  <ol style="padding-left:20px; line-height:1.7; font-size:13px; color:var(--ink); margin-bottom:18px;">
    <li>Chrome browser me naya tab kholein aur address bar me dalein: <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-weight:700; color:#1e293b;">chrome://extensions</code></li>
    <li>Top right corner me <b>"Developer mode"</b> toggle switch ko <b>ON</b> karein.</li>
    <li>Top left corner me <b>"Load unpacked"</b> button par click karein.</li>
    <li>Upar diya gaya folder select karein: <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-weight:700; color:#1e293b;">c:\xampp\htdocs\wsa-social\extension</code>.</li>
  </ol>

  <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:12px; margin-bottom:18px; font-size:12.5px; color:#065f46; line-height:1.5;">
    <b>🎉 Bas ho gaya!</b> Extension load hote hi background me active ho jayega. Har 2-minute ke interval pe queue se task uthayega, Facebook group kholega, "Post" button <b>automatically dabayega</b>, aur status report me update kar dega!
  </div>

  <div style="display:flex; justify-content:flex-end; gap:8px;">
    <button type="button" class="btn light" onclick="document.getElementById('modal-extension-setup').close()">Close</button>
  </div>
</dialog>

<script>
function copyExtensionFolder(btn) {
  const input = document.getElementById('ext-folder-path');
  if (input) {
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
      const orig = btn.innerText;
      btn.innerText = 'Copied! ✓';
      btn.style.background = '#10b981';
      setTimeout(() => {
        btn.innerText = orig;
        btn.style.background = '#2563eb';
      }, 2000);
    });
  }
}

let currentBoostUrl = '';
let currentBoostId = '';

function openBoostModal(postId, postUrl, snippet) {
  currentBoostId = postId;
  currentBoostUrl = postUrl;
  document.getElementById('boost_modal_snippet').innerText = snippet || 'Facebook Post/Reel';
  document.getElementById('boost_modal_link').href = postUrl || '#';
  document.getElementById('boost_input_post_id').value = postId || '';
  document.getElementById('boost_input_post_url').value = postUrl || '';
  document.getElementById('modal-boost-post').showModal();
}

function copyCommentText(btn, text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function() {
      const orig = btn.innerText;
      btn.innerText = 'Copied! ✓';
      btn.style.background = '#10b981';
      btn.style.color = '#fff';
      setTimeout(function() {
        btn.innerText = orig;
        btn.style.background = '#eff6ff';
        btn.style.color = '#1d4ed8';
      }, 2000);
    });
  }
}

function openReelAndCopyFirstComment() {
  const firstComment = "Outstanding work and strategic execution! Great job WSA! 🚀";
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(firstComment);
  }
  if (currentBoostUrl) {
    window.open(currentBoostUrl, '_blank');
  }
}

function onPostSelectChange(sel) {
  const val = sel.value;
  const urlInput = document.getElementById('queue_custom_url');
  const captionInput = document.getElementById('queue_caption');
  if (val === 'custom') {
    if (urlInput) {
      urlInput.value = '';
      urlInput.focus();
    }
  } else if (val.startsWith('campaign_')) {
    if (urlInput) urlInput.value = '';
  } else {
    if (urlInput) urlInput.value = val;
    const selectedOption = sel.options[sel.selectedIndex];
    const caption = selectedOption.getAttribute('data-caption') || '';
    if (captionInput && (!captionInput.value || captionInput.value.trim() === '')) {
      captionInput.value = caption;
    }
  }
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

// Open Facebook Group in new tab, copy post URL, and confirm 1-click share
function openGroupAndShare(btn, queueId, groupUrl, postUrl, senderLabel, groupName) {
  if (postUrl && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(postUrl);
  }
  if (groupUrl) {
    window.open(groupUrl, '_blank');
  }

  const origText = btn.innerHTML;
  const shortName = senderLabel ? (senderLabel.length > 14 ? senderLabel.substring(0, 12) + '...' : senderLabel) : 'Page';
  btn.innerHTML = 'Copied! Switch to ' + shortName + ' ✓';
  btn.style.background = '#10b981';

  setTimeout(function() {
    const doMark = confirm('🎉 Post URL copied aur ' + (groupName || 'Group') + ' new tab me open ho gaya hai!\n\n👉 Facebook par "' + senderLabel + '" profile se share karne ke baad, ise "Published Reports" me record karein?');
    if (doMark) {
      fetch('index.php?page=group-item-mark-shared-fast&queue_id=' + queueId + '&format=json')
        .then(function() {
          window.location.href = 'index.php?page=groups&tab=queue';
        }).catch(function() {
          window.location.href = 'index.php?page=groups&tab=queue';
        });
    } else {
      btn.innerHTML = origText;
      btn.style.background = '#1877f2';
    }
  }, 1000);
}

function openGroupAndCopy(btn, groupUrl, postUrl, senderLabel, groupName) {
  if (postUrl && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(postUrl);
  }
  if (groupUrl) {
    window.open(groupUrl, '_blank');
  }
}

// Toggle Group Selection Mode (Smart Rotation vs Manual)
function toggleGroupSelectionMode(mode) {
  const autoContainer = document.getElementById('container_group_mode_auto');
  const manualContainer = document.getElementById('container_group_mode_manual');
  const labelAuto = document.getElementById('label_mode_auto');
  const labelManual = document.getElementById('label_mode_manual');

  if (mode === 'manual') {
    if (autoContainer) autoContainer.style.display = 'none';
    if (manualContainer) manualContainer.style.display = 'block';
    if (labelAuto) {
      labelAuto.style.borderColor = 'var(--line)';
      const t = labelAuto.querySelector('div div');
      if (t) t.style.color = 'var(--ink)';
    }
    if (labelManual) {
      labelManual.style.borderColor = '#1877f2';
      const t = labelManual.querySelector('div div');
      if (t) t.style.color = '#1877f2';
    }
  } else {
    if (autoContainer) autoContainer.style.display = 'grid';
    if (manualContainer) manualContainer.style.display = 'none';
    if (labelAuto) {
      labelAuto.style.borderColor = '#1877f2';
      const t = labelAuto.querySelector('div div');
      if (t) t.style.color = '#1877f2';
    }
    if (labelManual) {
      labelManual.style.borderColor = 'var(--line)';
      const t = labelManual.querySelector('div div');
      if (t) t.style.color = 'var(--ink)';
    }
  }
  updateTotalScheduleCalculation();
}

// Filter Manual Groups List
function filterManualGroupList(query) {
  const q = (query || '').toLowerCase().trim();
  const items = document.querySelectorAll('.manual-group-item');
  items.forEach(function(item) {
    const name = item.getAttribute('data-group-name') || '';
    const niche = item.getAttribute('data-group-niche') || '';
    if (!q || name.includes(q) || niche.includes(q)) {
      item.style.display = 'flex';
    } else {
      item.style.display = 'none';
    }
  });
}

// Toggle All Manual Groups Checkboxes
function toggleAllManualGroups(check) {
  const cbs = document.querySelectorAll('.manual-group-checkbox');
  cbs.forEach(function(cb) {
    const parent = cb.closest('.manual-group-item');
    if (!parent || parent.style.display !== 'none') {
      cb.checked = check;
    }
  });
  updateTotalScheduleCalculation();
}

// Toggle All Sender Pages Checkboxes
function toggleAllSenderPages(btn) {
  const cbs = document.querySelectorAll('.sender-page-checkbox');
  const allChecked = Array.from(cbs).every(cb => cb.checked);
  cbs.forEach(cb => cb.checked = !allChecked);
  updateTotalScheduleCalculation();
}

// Update Total Schedule Calculation Text
function updateTotalScheduleCalculation() {
  const pageCheckedCount = document.querySelectorAll('.sender-page-checkbox:checked').length;
  const isManual = document.querySelector('input[name="group_selection_mode"]:checked')?.value === 'manual';
  const el = document.getElementById('schedule_calc_summary');
  const badge = document.getElementById('manual_group_badge');

  const manualCheckedCount = document.querySelectorAll('.manual-group-checkbox:checked').length;
  if (badge) {
    badge.innerText = manualCheckedCount + ' selected';
  }

  if (!el) return;

  if (isManual) {
    el.innerText = '🎯 Manual Mode: ' + manualCheckedCount + ' Groups chune gaye hain, jo ' + pageCheckedCount + ' Selected Pages me distribute honge.';
  } else {
    const sharesPerPage = parseInt(document.getElementById('queue_shares_per_page')?.value, 10) || 3;
    const total = pageCheckedCount * sharesPerPage;
    el.innerText = '🔄 Smart Rotation: ' + pageCheckedCount + ' Pages × ' + sharesPerPage + ' = ' + total + ' Groups (Har run me alag/fresh groups pick honge)';
  }
}

// Main Tabs Switching (Simple & Instant)
function switchMainTab(tabName) {
  if (!tabName) tabName = 'reels';
  const activeBtn = document.getElementById('tab-btn-' + tabName);
  const activePane = document.getElementById('tab-pane-' + tabName);
  if (!activePane) return;

  document.querySelectorAll('.groups-main-tab-btn').forEach(function(b) {
    b.classList.remove('active');
  });
  document.querySelectorAll('.groups-main-tab-pane').forEach(function(p) {
    p.style.display = 'none';
  });

  if (activeBtn) activeBtn.classList.add('active');
  activePane.style.display = 'block';

  try { 
    localStorage.setItem('wsa_groups_tab', tabName);
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
      switchMainTab(tabParam);
      return;
    }
    const saved = localStorage.getItem('wsa_groups_tab');
    if (saved && document.getElementById('tab-pane-' + saved)) {
      switchMainTab(saved);
      return;
    }
    switchMainTab('reels');
  } catch(e) {}
})();

// Account Dropdown Filter
function handleAccountDropdownFilter(accId) {
  const currentUrl = new URL(window.location.href);
  if (accId === 'all') {
    currentUrl.searchParams.delete('account_id');
  } else {
    currentUrl.searchParams.set('account_id', accId);
  }
  window.location.href = currentUrl.toString();
}

// Niche / Category Client Filter
function handleNicheFilter(niche) {
  const rows = document.querySelectorAll('#groups-table tbody tr:not(.empty-row)');
  let visible = 0;
  rows.forEach(function(r) {
    const rNiche = r.getAttribute('data-niche') || '';
    if (niche === 'all' || rNiche === niche) {
      r.style.display = '';
      visible++;
    } else {
      r.style.display = 'none';
    }
  });
}

function openAddGroupModal(tab) {
  document.getElementById('modal-add-group').showModal();
  switchGroupTab(tab || 'single');
}

function switchGroupTab(tab) {
  const singleForm = document.getElementById('form-single-group');
  const bulkForm = document.getElementById('form-bulk-groups');
  const singleBtn = document.getElementById('tab-btn-single');
  const bulkBtn = document.getElementById('tab-btn-bulk');

  if (tab === 'bulk') {
    singleForm.style.display = 'none';
    bulkForm.style.display = 'block';
    singleBtn.className = 'btn light';
    bulkBtn.className = 'btn btn-primary';
  } else {
    singleForm.style.display = 'block';
    bulkForm.style.display = 'none';
    singleBtn.className = 'btn btn-primary';
    bulkBtn.className = 'btn light';
  }
}

function switchQueueTab(tab) {
  const pendingPane = document.getElementById('pane-queue-pending');
  const publishedPane = document.getElementById('pane-queue-published');
  const approvalPane = document.getElementById('pane-queue-approval');
  const failedPane = document.getElementById('pane-queue-failed');
  const pendingBtn = document.getElementById('tab-btn-pending');
  const publishedBtn = document.getElementById('tab-btn-published');
  const approvalBtn = document.getElementById('tab-btn-approval');
  const failedBtn = document.getElementById('tab-btn-failed');

  if (pendingPane) pendingPane.style.display = (tab === 'pending') ? 'block' : 'none';
  if (publishedPane) publishedPane.style.display = (tab === 'published') ? 'block' : 'none';
  if (approvalPane) approvalPane.style.display = (tab === 'approval') ? 'block' : 'none';
  if (failedPane) failedPane.style.display = (tab === 'failed') ? 'block' : 'none';

  if (pendingBtn) pendingBtn.classList.toggle('active', tab === 'pending');
  if (publishedBtn) publishedBtn.classList.toggle('active', tab === 'published');
  if (approvalBtn) approvalBtn.classList.toggle('active', tab === 'approval');
  if (failedBtn) failedBtn.classList.toggle('active', tab === 'failed');
}

function openRecordShareModal(queueId, groupName, groupUrl, senderLabel, postUrl, defaultStatus) {
  document.getElementById('record_queue_id').value = queueId;
  const grpEl = document.getElementById('record_group_name');
  if (grpEl) grpEl.innerText = groupName || 'Facebook Group';
  const senderEl = document.getElementById('record_sender_name');
  if (senderEl) senderEl.innerText = senderLabel || 'Connected Page';
  const linkEl = document.getElementById('record_group_link');
  if (linkEl) {
    linkEl.href = groupUrl || '#';
    linkEl.innerText = (groupName || 'Facebook Group') + ' ↗';
  }
  const inputEl = document.getElementById('record_shared_url');
  if (inputEl) inputEl.value = '';

  const statusVal = defaultStatus || 'shared';
  const rShared = document.getElementById('status_radio_shared');
  const rPending = document.getElementById('status_radio_pending');
  if (statusVal === 'pending_approval') {
    if (rPending) rPending.checked = true;
  } else {
    if (rShared) rShared.checked = true;
  }

  document.getElementById('modal-record-share').showModal();
}

function openGroupAndShareManual(queueId, groupUrl, postUrl, senderLabel, groupName) {
  if (postUrl && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(postUrl);
  }
  if (groupUrl) {
    window.open(groupUrl, '_blank');
  }
  openRecordShareModal(queueId, groupName, groupUrl, senderLabel, postUrl, 'shared');
}

function copySyncScript(btn) {
  const code = `(async () => {
  const badge = document.createElement('div');
  badge.style.cssText = 'position:fixed;top:20px;right:20px;z-index:999999;background:#1877f2;color:#fff;padding:14px 20px;border-radius:12px;font-size:15px;font-weight:700;box-shadow:0 10px 25px rgba(0,0,0,0.3);font-family:sans-serif;';
  badge.innerHTML = '🔄 Loading all groups... <span id="wsa-count">0</span> found';
  document.body.appendChild(badge);

  const groups = new Map();
  function collect() {
    document.querySelectorAll('a[href*="/groups/"]').forEach(a => {
      const m = a.href.match(/\\/groups\\/([a-zA-Z0-9\\.\\_\\-]+)/);
      if (!m) return;
      const slug = m[1];
      if (['feed','joins','discover','create','search','category'].includes(slug.toLowerCase())) return;
      const url = 'https://www.facebook.com/groups/' + slug;
      let text = a.innerText.trim();
      if (!text || text.toLowerCase().includes('view group') || text.toLowerCase().includes('last visited')) {
        const card = a.closest('[role="listitem"]') || a.closest('[role="article"]') || a.parentElement?.parentElement;
        if (card) {
          const h = card.querySelector('span[dir="auto"], strong');
          if (h && h.innerText.trim()) text = h.innerText.trim();
        }
      }
      text = text.replace(/(Last active|You last visited|last visited).*$/i, '').trim();
      if (text && text.toLowerCase() !== 'view group' && !groups.has(url)) {
        groups.set(url, { name: text, url: url });
      }
    });
    const el = document.getElementById('wsa-count');
    if (el) el.innerText = groups.size;
  }

  let lastHeight = 0, sameCount = 0;
  for (let step = 0; step < 35; step++) {
    window.scrollBy(0, window.innerHeight * 3);
    collect();
    await new Promise(r => setTimeout(r, 500));
    const currentHeight = document.body.scrollHeight;
    if (currentHeight === lastHeight) {
      if (++sameCount >= 4) break;
    } else {
      sameCount = 0;
      lastHeight = currentHeight;
    }
  }

  collect();
  badge.innerHTML = '⚡ Syncing ' + groups.size + ' groups to WSA Social...';

  const list = Array.from(groups.values());
  const res = await fetch('http://localhost/wsa-social/index.php?page=api-import-groups', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ groups: list })
  });
  const json = await res.json();
  badge.style.background = '#10b981';
  badge.innerHTML = '🎉 Success! ' + (json.added !== undefined ? json.added : list.length) + ' Groups Imported!';
  setTimeout(() => badge.remove(), 7000);
  alert('🎉 Superb! ' + (json.added !== undefined ? json.added : list.length) + ' Groups WSA Social me successfully sync ho gaye!');
})();`;

  navigator.clipboard.writeText(code).then(function() {
    const orig = btn.innerText;
    btn.innerText = '✓ Script Copied!';
    btn.style.color = '#10b981';
    setTimeout(function() {
      btn.innerText = orig;
      btn.style.color = '';
    }, 3000);
  }).catch(function() {
    prompt('Copy this code:', code);
  });
}
</script>
