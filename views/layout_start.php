<?php
declare(strict_types=1);

/**
 * @var string $title
 * @var string $activePage
 */
$adminName = h($_SESSION['admin_name'] ?? 'Administrator');
$nav = [
    ['index.php', 'Dashboard', 'dashboard', 'cpu'],
    ['index.php?page=groups', 'Facebook Groups', 'groups', 'share'],
    ['index.php?page=campaigns', 'Create & Schedule Post', 'campaigns', 'sparkles'],
    ['index.php?page=comments', 'Likes & Comments', 'comments', 'brain'],
    ['index.php?page=accounts', 'Connected Accounts', 'accounts', 'accounts'],
    ['index.php?page=clients', 'Clients / Brands', 'clients', 'clients'],
    ['index.php?page=manual', 'Direct Post', 'manual', 'manual'],
    ['index.php?page=reports', 'Reports & History', 'reports', 'reports'],
    ['index.php?page=settings', 'Settings', 'settings', 'settings'],
];

// Check Runner Heartbeat Status
$heartbeat = get_system_setting('runner_last_heartbeat');
$runnerOnline = false;
$runnerStatusText = 'Runner Standby';
if ($heartbeat) {
    $diff = time() - strtotime($heartbeat);
    if ($diff <= 180) { // Considered online if ran within last 3 minutes
        $runnerOnline = true;
        $runnerStatusText = 'Sync Active (' . $diff . 's ago)';
    } else {
        $runnerStatusText = 'Sync Standby (' . round($diff / 60) . 'm ago)';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="WSA Multi-Channel Publisher - Enterprise AI social media, blogging, and multi-network management platform.">
  <title><?= h($title) ?> - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/../assets/app.css') ?>">
  <script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>" defer></script>
  <script>
  (function(){
      const t = localStorage.getItem("wsa_theme");
      if (t === "dark" || (!t && window.matchMedia("(prefers-color-scheme: dark)").matches)) {
          document.documentElement.setAttribute("data-theme", "dark");
      }
  })();
  </script>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="app">
    <aside aria-label="Primary Navigation">
      <div class="brand">
        <span class="brand-logo" style="background:#1877f2; color:#fff; display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px;">
          <?= Icons::platform('facebook', 18) ?>
        </span>
        <div>
          <b style="display:flex; align-items:center; gap:6px; font-size:15px; color:#ffffff;">
            <span>WSA Social</span>
          </b>
          <small style="color:#94a3b8; font-size:11px;">Marketing &amp; Groups Manager</small>
        </div>
      </div>
      <nav>
        <?php foreach ($nav as [$href, $label, $pKey, $iconKey]): ?>
          <a href="<?= h($href) ?>"<?= ($pKey === $activePage) ? ' aria-current="page"' : '' ?>>
            <?= Icons::svg($iconKey, 18, 'nav-icon') ?>
            <span><?= h($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="aside-bottom">
        <div style="padding:8px 4px 12px;border-top:1px solid rgba(255,255,255,0.08);margin-bottom:6px;">
          <div style="font-weight:700;text-transform:uppercase;letter-spacing:0.08em;font-size:9.5px;color:#64748b;margin-bottom:4px;">Legal &amp; Compliance</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:11px;">
            <a href="index.php?page=privacy" style="padding:0;color:#94a3b8;font-size:11px;display:inline;" target="_blank">Privacy</a>
            <span style="color:#475569;">•</span>
            <a href="index.php?page=terms" style="padding:0;color:#94a3b8;font-size:11px;display:inline;" target="_blank">Terms</a>
            <span style="color:#475569;">•</span>
            <a href="index.php?page=data-deletion" style="padding:0;color:#94a3b8;font-size:11px;display:inline;" target="_blank">Data Deletion</a>
          </div>
        </div>
        <form method="post" action="index.php?page=logout" class="form-inline">
          <?= csrf_field() ?>
          <button type="submit" class="logout-btn">
            <?= Icons::svg('logout', 18, 'nav-icon') ?>
            <span>Logout</span>
          </button>
        </form>
      </div>
    </aside>
    <main id="main-content">
      <header>
        <div class="header-left">
          <button type="button" class="nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
            <?= Icons::svg('menu', 20) ?>
          </button>
          <b><?= h($title) ?></b>
        </div>
        <div class="header-right">
          <form method="get" action="index.php" class="workspace-switcher-form">
            <input type="hidden" name="page" value="switch-workspace">
            <input type="hidden" name="return" value="<?= h($activePage) ?>">
            <label for="header_ws_select" class="sr-only">Active Workspace</label>
            <div class="workspace-switcher-pill">
              <?= Icons::svg('dashboard', 13, 'ws-icon') ?>
              <select id="header_ws_select" name="client_id" onchange="this.form.submit()" aria-label="Switch Active Workspace">
                <option value="0" <?= ($activeClientId ?? 0) === 0 ? 'selected' : '' ?>>🌐 All Workspaces (Unified)</option>
                <?php if (!empty($allClients)): ?>
                  <optgroup label="Select Workspace">
                    <?php foreach ($allClients as $cl): ?>
                      <option value="<?= (int)$cl['id'] ?>" <?= ($activeClientId ?? 0) === (int)$cl['id'] ? 'selected' : '' ?>>
                        🏢 <?= h($cl['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
            </div>
          </form>

          <span class="runner-status-pill <?= $runnerOnline ? 'online' : 'offline' ?>" title="Background Queue Worker Status">
            <span class="pulse-dot"></span> <?= h($runnerStatusText) ?>
          </span>
          <form method="post" action="index.php?page=run-queue" style="margin:0; display:inline-flex;">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= h($activePage) ?>">
            <button type="submit" class="runner-sync-btn" title="Sync & Publish all due scheduled posts right now">
              <?= Icons::svg('sparkles', 12) ?> <span>Sync Now</span>
            </button>
          </form>
          <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle visual theme">
            <span class="theme-icon-slot"><?= Icons::svg('moon', 16) ?></span>
          </button>
          <div class="user">
            <?= Icons::svg('user', 14, 'user-icon') ?>
            <span><?= $adminName ?></span>
          </div>
        </div>
      </header>
      <section class="content">
        <?php if ($s = flash('success')): ?>
          <div class="alert ok" role="status">
            <?= Icons::svg('check', 16, 'alert-icon') ?>
            <span><?= h($s) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($e = flash('error')): ?>
          <div class="alert err" role="alert">
            <?= Icons::svg('shield', 16, 'alert-icon') ?>
            <span><?= h($e) ?></span>
          </div>
        <?php endif; ?>
