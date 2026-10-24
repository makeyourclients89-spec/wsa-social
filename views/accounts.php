<?php
declare(strict_types=1);

/**
 * @var array $clients
 * @var array $accounts
 */
$metaConfigured = MetaGraph::isConfigured(db());
$linkedinConfigured = LinkedInClient::isConfigured(db());
$pinterestConfigured = PinterestClient::isConfigured(db());

// Inspect one sample Meta token to verify whether pages_manage_posts is granted
$metaSampleToken = '';
foreach ($accounts as $accRow) {
    if (in_array((string)$accRow['platform'], ['facebook', 'instagram'], true) && !empty($accRow['access_token'])) {
        $metaSampleToken = (string)$accRow['access_token'];
        break;
    }
}
$metaScopeInfo = null;
$metaHasPostScope = true;
if ($metaSampleToken !== '' && $metaConfigured) {
    $metaScopeInfo = MetaGraph::inspectTokenScopes(db(), $metaSampleToken);
    if (!empty($metaScopeInfo['is_valid'])) {
        $metaHasPostScope = !empty($metaScopeInfo['has_post_perm']);
    }
}
?>
<div class="hero small">
  <div>
    <span class="eyebrow">Connection Center</span>
    <h1>Connect / Manage Accounts</h1>
    <p>Official OAuth 2.0 multi-network integration for Meta (Facebook & Instagram), LinkedIn, and Pinterest.</p>
  </div>
</div>

<?php if ($metaSampleToken !== '' && !$metaHasPostScope): ?>
<div class="card mb-20" style="border-left: 5px solid #d97706; background: rgba(245, 158, 11, 0.08); margin-bottom: 20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
    <div style="flex:1; min-width:260px;">
      <h2 style="color:#b45309; margin:0 0 6px; font-size:15px;">⚠️ Aapke 17 Connected Pages Abhi "Read-Only Mode" Me Hain (Posting Permission Missing!)</h2>
      <p style="margin:0; font-size:12.5px; line-height:1.5; color:var(--ink);">
        Aapke pages me jo purana Token save hai usme sirf <code>pages_show_list</code> aur <code>pages_read_engagement</code> hai, lekin auto-post karne ke liye <b><code>pages_manage_posts</code></b> permission nahi hai. Isiliye Facebook post block kar raha hai.<br>
        👉 Niche <b>1-Click Upgrade Token</b> button dabayein (ya Direct Token box me naya token dalein) — saare 17 pages turant Auto-Posting ke liye active ho jayenge!
      </p>
    </div>
    <div>
      <a href="index.php?page=meta-connect&amp;client_id=<?= (int)($activeClientId ?: ($clients[0]['id'] ?? 2)) ?>&amp;scope_mode=full" class="btn" style="background:#1877f2; color:#fff; text-decoration:none; font-weight:700; padding:10px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
        <?= Icons::platform('facebook', 15) ?>
        1-Click Upgrade Token (Enable Auto-Posting)
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 20px;">
  <!-- Meta Realtime OAuth Integration Card -->
  <div class="card">
    <div class="cardhead">
      <div>
        <h2 style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700;">
          <?= Icons::platform('facebook', 18) ?> Meta (Facebook &amp; Instagram)
        </h2>
        <p>Auto-posting &amp; commenting via official Meta Graph API</p>
      </div>
      <div>
        <?php if ($metaConfigured): ?>
          <span class="badge-active">OAuth Ready</span>
        <?php else: ?>
          <span class="badge-inactive">Config Needed</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$metaConfigured): ?>
      <p style="margin: 0 0 12px; font-size: 12px; color: var(--muted);">
        Meta App ID/Secret missing. Configure in <a href="index.php?page=settings" style="color:var(--blue);font-weight:700;">Settings</a> to connect live accounts.
      </p>
    <?php else: ?>
      <form method="get" action="index.php" style="display: flex; flex-direction: column; gap: 10px;">
        <input type="hidden" name="page" value="meta-connect">
        <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
          <label for="meta_client_select" style="margin:0; flex:1; min-width: 130px;">Workspace:
            <select id="meta_client_select" name="client_id" required style="margin-top:4px;">
              <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($activeClientId ?? 0) === (int)$c['id'] || (empty($activeClientId) && $c['name'] === 'WSA Digital Agency')) ? 'selected' : '' ?>>
                  <?= h($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label for="meta_scope_mode" style="margin:0; flex:1; min-width: 170px;">Mode:
            <select id="meta_scope_mode" name="scope_mode" style="margin-top:4px;">
              <option value="full" selected>🚀 Full Automation (Post &amp; Comments) [Recommended]</option>
              <option value="essential">⚡ Read Only (View Pages &amp; Stats Only)</option>
            </select>
          </label>
        </div>
        <p style="margin:4px 0 8px; font-size:11.5px; color:var(--muted); line-height:1.4;">
          💡 <b>Tip:</b> Auto-posting ke liye Meta se <code>pages_manage_posts</code> permission chahiye hoti hai. Isliye <b>Full Automation</b> select karke connect karein.
        </p>
        <button type="submit" class="btn" style="justify-content:center; width:100%;">
          <?= Icons::platform('facebook', 14) ?>
          Connect Meta (Facebook &amp; Instagram)
        </button>
      </form>

      <!-- Direct Token Sync & "App Not Active" Bypass Box -->
      <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--line);">
        <button type="button" onclick="var el=document.getElementById('direct-token-box'); el.style.display=(el.style.display==='none'?'block':'none');" style="background:none; border:none; color:var(--blue); font-size:12px; font-weight:700; cursor:pointer; padding:0; display:flex; align-items:center; gap:5px;">
          <span>🔑 Dusri ID pe "App Not Active" aa raha hai? (Click Here for Instant Bypass)</span>
        </button>

        <div id="direct-token-box" style="display:none; margin-top:10px; background:var(--bg); border:1px solid var(--line); border-radius:10px; padding:12px;">
          <div style="font-size:11.5px; color:var(--ink); line-height:1.45; margin-bottom:10px;">
            <b> तरीका 1 (Meta App Settings - 30 Sec):</b><br>
            Jab App <i>Development Mode</i> me hota hai, tab sirf Admin ID connect hoti hai. Dusri ID allow karne ke liye:<br>
            1. <a href="https://developers.facebook.com/apps/1090257013843594/roles/roles/" target="_blank" rel="noopener" style="color:var(--blue); font-weight:700;">Meta App Roles खोलें ↗</a> और दूसरी Facebook ID को <b>Administrator / Tester</b> में जोड़ दें (या ऊपर Top Bar में App Mode को <b>Live</b> कर दें)।<br><br>
            <b>तरीका 2 (Direct Token Sync - Zero Popup):</b><br>
            <a href="https://developers.facebook.com/tools/explorer/?method=GET&path=me%2Faccounts&version=v19.0" target="_blank" rel="noopener" style="color:var(--blue); font-weight:700;">Meta Graph Explorer ↗</a> से Token कॉपी करके नीचे पेस्ट करें — सिस्टम आपकी App ID &amp; Secret से उसे खुद <b>60-Day Permanent Token</b> में बदल कर सारे Pages जोड़ देगा:
          </div>

          <form method="post" action="index.php?page=meta-direct-sync" style="display:flex; flex-direction:column; gap:8px;">
            <?= csrf_field() ?>
            <input type="hidden" name="client_id" value="<?= (int)($activeClientId ?: ($clients[0]['id'] ?? 2)) ?>">
            <input type="text" name="direct_meta_token" required placeholder="Paste EAA... User or Page Access Token here" style="font-size:12px; padding:8px 10px;">
            <button type="submit" class="btn" style="justify-content:center; padding:8px 12px; font-size:12px;">
              ⚡ Auto-Upgrade with App Secret &amp; Sync All Pages
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- LinkedIn OAuth 2.0 Integration Card -->
  <div class="card">
    <div class="cardhead">
      <div>
        <h2 style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700;">
          <?= Icons::platform('linkedin', 18) ?> LinkedIn Professional
        </h2>
        <p>Auto-posting &amp; commenting on member profiles &amp; pages</p>
      </div>
      <div>
        <?php if ($linkedinConfigured): ?>
          <span class="badge-active">OAuth Ready</span>
        <?php else: ?>
          <span class="badge-inactive">Config Needed</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$linkedinConfigured): ?>
      <p style="margin: 0 0 12px; font-size: 12px; color: var(--muted);">
        LinkedIn Client ID/Secret missing. Configure in <a href="index.php?page=settings" style="color:var(--blue);font-weight:700;">Settings</a> to connect live accounts.
      </p>
    <?php else: ?>
      <form method="get" action="index.php" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="page" value="linkedin-connect">
        <label for="li_client_select" style="margin:0; flex:1; min-width: 140px;">Workspace:
          <select id="li_client_select" name="client_id" required style="margin-top:4px;">
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (($activeClientId ?? 0) === (int)$c['id'] || (empty($activeClientId) && $c['name'] === 'WSA Digital Agency')) ? 'selected' : '' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn" style="width:100%; justify-content:center; margin-top:8px;">
          <?= Icons::platform('linkedin', 14) ?>
          Connect LinkedIn
        </button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Pinterest OAuth 2.0 Integration Card -->
  <div class="card">
    <div class="cardhead">
      <div>
        <h2 style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700;">
          <?= Icons::platform('pinterest', 18) ?> Pinterest Business &amp; Boards
        </h2>
        <p>Pin creation, multi-board sync &amp; comment replies</p>
      </div>
      <div>
        <?php if ($pinterestConfigured): ?>
          <span class="badge-active">OAuth Ready</span>
        <?php else: ?>
          <span class="badge-inactive">Config Needed</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$pinterestConfigured): ?>
      <p style="margin: 0 0 12px; font-size: 12px; color: var(--muted);">
        Pinterest App ID/Secret missing. Configure in <a href="index.php?page=settings" style="color:var(--blue);font-weight:700;">Settings</a> to connect live boards.
      </p>
    <?php else: ?>
      <form method="get" action="index.php" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="page" value="pinterest-connect">
        <label for="pin_client_select" style="margin:0; flex:1; min-width: 140px;">Workspace:
          <select id="pin_client_select" name="client_id" required style="margin-top:4px;">
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (($activeClientId ?? 0) === (int)$c['id'] || (empty($activeClientId) && $c['name'] === 'WSA Digital Agency')) ? 'selected' : '' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn" style="width:100%; justify-content:center; margin-top:8px;">
          <?= Icons::platform('pinterest', 14) ?>
          Connect Pinterest
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="channelgrid big">
  <?php foreach (platforms() as $key => $p): ?>
    <div class="channel cardchannel">
      <span class="channel-icon-wrap channel-<?= h($key) ?>"><?= Icons::platform($key, 18) ?></span>
      <div>
        <b><?= h($p[0]) ?></b>
        <small><?= h(ucfirst($p[1])) ?></small>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid two">
  <form class="card" method="post" action="index.php?page=account-add">
    <?= csrf_field() ?>
    <h2>Add Social Page / Profile</h2>
    <label for="acc_client">Client Workspace
      <select id="acc_client" name="client_id" required>
        <option value="">Select Workspace...</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (($activeClientId ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label for="acc_platform">Platform
      <select id="acc_platform" name="platform" required>
        <?php foreach (platforms() as $k => $p): ?>
          <option value="<?= h($k) ?>"><?= h($p[0]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label for="acc_label">Page / Account Label
      <input id="acc_label" name="label" required placeholder="e.g. WSA Digital Agency (Page)">
    </label>
    <div class="twocol">
      <label for="acc_ext">Page ID / Profile Handle
        <input id="acc_ext" name="external_id" placeholder="e.g. 573548745852007 or @online.marketing.by.wsadigital">
      </label>
      <label for="acc_cat">Category / Niche
        <input id="acc_cat" name="page_category" placeholder="e.g. Technology & Code">
      </label>
    </div>
    <label for="acc_token">Direct API Access Token (Optional)
      <input id="acc_token" name="access_token" type="password" placeholder="Paste Page Access Token for instant Live API mode">
      <span class="media-hint">If provided, auto-publishing & auto-commenting activate immediately for this page.</span>
    </label>
    <div class="twocol">
      <label for="acc_base">Starting Daily Limit
        <input id="acc_base" type="number" name="daily_base_limit" value="5" min="1">
      </label>
      <label for="acc_max">Maximum Daily Limit
        <input id="acc_max" type="number" name="daily_max_limit" value="15" min="1">
      </label>
    </div>
    <button type="submit">Add Social Page</button>
  </form>

  <div class="card">
    <div class="cardhead">
      <div>
        <h2>Account Pool</h2>
        <p>Configured channels across all client brands</p>
      </div>
      <div class="table-search-bar">
        <input type="search" class="table-search-input" placeholder="Search accounts..." data-table-filter="accounts-table" aria-label="Search accounts">
      </div>
    </div>

    <?php
      $fbCount = count(array_filter($accounts, fn($x) => $x['platform'] === 'facebook'));
      $igCount = count(array_filter($accounts, fn($x) => $x['platform'] === 'instagram'));
      $liCount = count(array_filter($accounts, fn($x) => $x['platform'] === 'linkedin'));
      $pinCount = count(array_filter($accounts, fn($x) => $x['platform'] === 'pinterest'));
      $otherCount = count(array_filter($accounts, fn($x) => !in_array($x['platform'], ['facebook', 'instagram', 'linkedin', 'pinterest'], true)));
    ?>

    <!-- Category Filter Tabs -->
    <div class="account-filter-tabs">
      <button type="button" class="tab-pill active" onclick="filterAccountRows('all', this)">All Channels (<?= count($accounts) ?>)</button>
      <button type="button" class="tab-pill" onclick="filterAccountRows('facebook', this)">
        <?= Icons::platform('facebook', 12) ?> Facebook Pages (<?= $fbCount ?>)
      </button>
      <button type="button" class="tab-pill" onclick="filterAccountRows('instagram', this)">
        <?= Icons::platform('instagram', 12) ?> Instagram (<?= $igCount ?>)
      </button>
      <button type="button" class="tab-pill" onclick="filterAccountRows('linkedin', this)">
        <?= Icons::platform('linkedin', 12) ?> LinkedIn (<?= $liCount ?>)
      </button>
      <button type="button" class="tab-pill" onclick="filterAccountRows('pinterest', this)">
        <?= Icons::platform('pinterest', 12) ?> Pinterest (<?= $pinCount ?>)
      </button>
      <?php if ($otherCount > 0): ?>
        <button type="button" class="tab-pill" onclick="filterAccountRows('other', this)">Other (<?= $otherCount ?>)</button>
      <?php endif; ?>
    </div>

    <div class="tablewrap">
      <table id="accounts-table">
        <thead>
          <tr>
            <th scope="col">Page / Profile</th>
            <th scope="col">Platform</th>
            <th scope="col">Workspace</th>
            <th scope="col">API Status</th>
            <th scope="col">Daily Limit</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $a): ?>
            <?php
              $hasToken = !empty($a['access_token']);
              $p = (string)$a['platform'];
              if ($hasToken && in_array($p, ['facebook', 'instagram'], true)) {
                  if ($metaHasPostScope) {
                      $connBadge = '<span class="badge-meta">✅ Auto-Post Ready</span>';
                  } else {
                      $connBadge = '<span class="badge-inactive" style="background:#fef3c7;color:#b45309;border:1px solid #f59e0b;">⚠️ Read-Only Token</span>';
                  }
              } elseif ($hasToken && $p === 'linkedin') {
                  $connBadge = '<span class="badge-linkedin">Live LinkedIn API</span>';
              } elseif ($hasToken && $p === 'pinterest') {
                  $connBadge = '<span class="badge-pinterest">Live Pinterest API</span>';
              } else {
                  $connBadge = '<span class="badge-inactive">Standard</span>';
              }
            ?>
            <tr data-platform="<?= h($p) ?>">
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span class="channel-icon-wrap channel-<?= h($p) ?>" style="width:28px;height:28px;border-radius:6px;">
                    <?= Icons::platform($p, 14) ?>
                  </span>
                  <div>
                    <b><?= h($a['label']) ?></b>
                    <div style="display:flex;gap:6px;align-items:center;margin-top:2px;">
                      <?php if (!empty($a['external_id'])): ?>
                        <small class="text-muted" style="font-size:10px;">ID: <?= h($a['external_id']) ?></small>
                      <?php endif; ?>
                      <?php if (!empty($a['page_category'])): ?>
                        <span class="category-pill"><?= h($a['page_category']) ?></span>
                      <?php endif; ?>
                      <?php 
                        $grpCnt = (int)($fbGroupCounts[$a['id']] ?? 0);
                        if ($p === 'facebook' && $grpCnt > 0): 
                      ?>
                        <a href="index.php?page=groups&account_id=<?= (int)$a['id'] ?>" style="text-decoration:none;" title="View all <?= $grpCnt ?> joined Facebook groups for this account">
                          <span class="category-pill" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:700;">
                            👥 <?= $grpCnt ?> Groups
                          </span>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span class="platform-chip"><?= Icons::platform($p, 12, 'chip-icon') ?> <?= h(platforms()[$p][0] ?? $p) ?></span>
              </td>
              <td><?= h($a['client_name']) ?></td>
              <td><?= $connBadge ?></td>
              <td><span style="font-size:11px;"><?= (int)$a['daily_base_limit'] ?> / <?= (int)$a['daily_max_limit'] ?> posts</span></td>
              <td>
                <div style="display:flex;gap:6px;align-items:center;">
                  <?php if ($p === 'facebook'): ?>
                    <a href="index.php?page=groups&account_id=<?= (int)$a['id'] ?>" class="smallbtn" style="background:#1877f2;color:#ffffff;text-decoration:none;padding:5px 9px;border-radius:5px;font-weight:700;display:inline-flex;align-items:center;gap:4px;" title="View joined Facebook Groups for this profile">
                      👥 Groups (<?= (int)($fbGroupCounts[$a['id']] ?? 0) ?>)
                    </a>
                  <?php endif; ?>
                  <?php if ($hasToken && in_array($p, ['facebook', 'instagram', 'linkedin', 'pinterest'], true)): ?>
                    <a href="index.php?page=comments&account_id=<?= (int)$a['id'] ?>" class="smallbtn" style="background:#0369a1;color:#ffffff;text-decoration:none;padding:5px 9px;border-radius:5px;font-weight:700;" title="Comment using this page">
                      Comment
                    </a>
                  <?php endif; ?>
                  <form method="post" action="index.php?page=account-delete" class="form-inline" data-confirm="Delete this account profile? Connected jobs may be impacted.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="smallbtn danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($accounts)): ?>
            <tr class="empty-row"><td colspan="6" class="empty">No account profiles connected yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function filterAccountRows(filter, btn) {
  document.querySelectorAll('.account-filter-tabs .tab-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const rows = document.querySelectorAll('#accounts-table tbody tr:not(.empty-row)');
  rows.forEach(r => {
    const plat = r.getAttribute('data-platform');
    if (filter === 'all') {
      r.style.display = '';
    } else if (filter === 'other') {
      r.style.display = (!['facebook', 'instagram', 'linkedin', 'pinterest'].includes(plat)) ? '' : 'none';
    } else {
      r.style.display = (plat === filter) ? '' : 'none';
    }
  });
}
</script>
