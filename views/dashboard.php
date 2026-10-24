<?php
declare(strict_types=1);

/**
 * @var array $stats
 * @var array $jobs
 * @var array $accts
 * @var array $acts
 */
?>
<div class="hero">
  <div>
    <h1 style="letter-spacing:-0.02em; margin:0 0 6px; font-size:24px; font-weight:800;">
      👋 Welcome to WSA Social
    </h1>
    <p style="font-size:13.5px; color:var(--muted); margin:0;">
      Apne sabhi social accounts, scheduled posts aur Facebook group shares ka overview yahan dekhein.
    </p>
  </div>
  <div class="actions" style="display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="index.php?page=groups" style="background:#1877f2; color:#fff; text-decoration:none; font-weight:700; padding:9px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
      <?= Icons::platform('facebook', 15) ?>
      <span>Facebook Groups</span>
    </a>
    <a class="btn light" href="index.php?page=campaigns" style="padding:9px 16px; font-weight:600; text-decoration:none; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
      <?= Icons::svg('sparkles', 15) ?>
      <span>+ Nayi Post Banayein</span>
    </a>
    <a class="btn light" href="index.php?page=comments" style="padding:9px 16px; font-weight:600; text-decoration:none; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
      <?= Icons::svg('brain', 15) ?>
      <span>Likes &amp; Comments</span>
    </a>
  </div>
</div>

<?php
$statMeta = [
    'Clients'        => ['Clients', 'accent-blue', 'blue', 'clients', 'Total Clients'],
    'Accounts'       => ['Connected Accounts', 'accent-violet', 'violet', 'accounts', 'Social Profiles'],
    'Scheduled'      => ['Scheduled Posts', 'accent-blue', 'blue', 'clock', 'In Queue'],
    'Published'      => ['Published Posts', 'accent-emerald', 'emerald', 'check', 'Live on Social'],
    'Manual Pending' => ['Pending Review', 'accent-amber', 'amber', 'manual', 'Manual Dispatch'],
    'Failed'         => ['Failed / Deferred', 'accent-amber', 'amber', 'shield', 'Action Needed'],
];
?>
<div class="kpis" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 24px;">
  <?php foreach ($stats as $k => $v): ?>
    <?php 
      $meta = $statMeta[$k] ?? [$k, 'accent-blue', 'blue', 'sparkles', 'Workspace Total'];
    ?>
    <div class="enterprise-kpi-card <?= $meta[1] ?>">
      <div class="kpi-header-row">
        <span class="kpi-label-text"><?= h($meta[0]) ?></span>
        <span class="kpi-icon-pill <?= $meta[2] ?>"><?= Icons::svg($meta[3], 14) ?></span>
      </div>
      <div class="kpi-metric-number">
        <?= (int)$v ?>
      </div>
      <div style="font-size:11px; color:var(--muted); margin-top: auto;">
        <?= h($meta[4]) ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid two">
  <div class="card">
    <div class="cardhead">
      <div>
        <h2>Recent Publishing Queue</h2>
        <p>Latest scheduled and assisted jobs</p>
      </div>
      <a href="index.php?page=manual">Open queue</a>
    </div>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th scope="col">Campaign</th>
            <th scope="col">Client</th>
            <th scope="col">Platform</th>
            <th scope="col">Status</th>
            <th scope="col">Schedule</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $j): ?>
            <tr>
              <td><b><?= h($j['campaign_name']) ?></b></td>
              <td><?= h($j['client_name']) ?></td>
              <td><span class="pill"><?= h(platforms()[$j['platform']][0] ?? $j['platform']) ?></span></td>
              <td><span class="status"><?= h($j['status']) ?></span></td>
              <td><?= h($j['scheduled_for']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($jobs)): ?>
            <tr class="empty-row"><td colspan="5" class="empty">No jobs yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="cardhead">
      <div>
        <h2>20 Channels</h2>
        <p>Social, blogging and image platforms</p>
      </div>
    </div>
    <div class="channelgrid">
      <?php foreach (platforms() as $key => $p): ?>
        <a class="channel" href="index.php?page=accounts">
          <span class="channel-icon-wrap channel-<?= h($key) ?>"><?= Icons::platform($key, 18) ?></span>
          <div>
            <b><?= h($p[0]) ?></b>
            <small><?= (int)($accts[$key] ?? 0) ?> account(s)</small>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card mt-20">
  <div class="cardhead">
    <div>
      <h2>Live Activity Audit Trail</h2>
      <p>Real-time system event logging, campaign execution, and OAuth sync history</p>
    </div>
    <?php if (!empty($acts)): ?>
      <form method="POST" action="index.php?page=activity-clear" onsubmit="return confirm('Clear activity audit history?');" style="margin:0;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline smallbtn" style="color:var(--muted); font-size:11px; padding:4px 10px; display:inline-flex; align-items:center; gap:5px;">
          <?= Icons::svg('trash', 12) ?> Clear History
        </button>
      </form>
    <?php endif; ?>
  </div>
  <div class="audit-feed">
    <?php foreach ($acts as $ac): ?>
      <?php
        $status = strtolower((string)$ac['status']);
        $rawMsg = (string)$ac['message'];
        $iconName = 'zap';
        $iconClass = 'info';

        if ($status === 'success') {
            $iconName = 'check';
            $iconClass = 'success';
        } elseif ($status === 'error') {
            $iconName = 'x-circle';
            $iconClass = 'error';
        } elseif ($status === 'warning') {
            $iconName = 'shield';
            $iconClass = 'warning';
        }

        // Parse title and technical details if present
        $title = $rawMsg;
        $details = null;
        $liveLink = null;

        if (strpos($rawMsg, ':') !== false) {
            $parts = explode(':', $rawMsg, 2);
            $prefix = trim($parts[0]);
            $body = trim($parts[1]);

            if (strlen($body) > 70 || stripos($body, 'API Error') !== false || stripos($body, '{') !== false) {
                $title = $prefix;
                $details = $body;
            } else {
                $title = $rawMsg;
            }
        }

        // Check for live Facebook post ID pattern (e.g. 1483881688312170_1426112646276280)
        if (preg_match('/(\d{10,20}_\d{10,20})/', $rawMsg, $m)) {
            $liveLink = 'https://www.facebook.com/' . $m[1];
        }

        $cName = !empty($ac['client_name']) ? h($ac['client_name']) : '';
        $timeStr = !empty($ac['created_at']) ? date('M j, Y • H:i', strtotime($ac['created_at'])) : '';
      ?>
      <div class="audit-item">
        <div class="audit-item-left">
          <div class="audit-item-icon <?= $iconClass ?>">
            <?= Icons::svg($iconName, 16) ?>
          </div>
          <div class="audit-item-body">
            <div class="audit-item-title">
              <span><?= h($title) ?></span>
              <?php if ($cName): ?>
                <span class="badge" style="font-size:10px; font-weight:600; padding:1px 6px; background:#f1f5f9; color:#475569; border-radius:4px;"><?= $cName ?></span>
              <?php endif; ?>
              <?php if ($liveLink): ?>
                <a href="<?= h($liveLink) ?>" target="_blank" rel="noopener" class="job-link" style="display:inline-flex; align-items:center; gap:3px; margin-left:4px;">
                  <?= Icons::svg('link', 11) ?> View Post ↗
                </a>
              <?php endif; ?>
            </div>
            <?php if ($details): ?>
              <details class="audit-details">
                <summary>View Technical Details</summary>
                <pre><?= h($details) ?></pre>
              </details>
            <?php endif; ?>
          </div>
        </div>
        <div class="audit-item-right">
          <span class="status status-<?= h($status) ?>"><?= ucfirst(h($status)) ?></span>
          <span class="audit-item-time"><?= $timeStr ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($acts)): ?>
      <div class="empty" style="text-align:center; padding:32px 16px; color:var(--muted);">
        <?= Icons::svg('check-circle', 32) ?><br><br>
        No activity records logged yet. Your system is all clear!
      </div>
    <?php endif; ?>
  </div>
</div>

