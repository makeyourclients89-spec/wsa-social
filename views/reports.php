<?php
declare(strict_types=1);

/**
 * @var array $rows
 */
?>
<div class="hero small">
  <div>
    <span class="eyebrow">Analytics & Audit</span>
    <h1>Publishing Reports</h1>
    <p>Aggregate job status and publication metrics across all channels.</p>
  </div>
  <div class="actions">
    <a class="btn light" href="index.php?page=export-csv">
      <?= Icons::svg('reports', 15) ?>
      <span>Export CSV Report</span>
    </a>
  </div>
</div>

<div class="card">
  <div class="cardhead">
    <div>
      <h2>Publishing Report</h2>
      <p>Performance telemetry aggregated by network and status</p>
    </div>
    <div class="table-search-bar">
      <input type="search" class="table-search-input" placeholder="Search report metrics..." data-table-filter="reports-table" aria-label="Search reports">
    </div>
  </div>
  <div class="tablewrap">
    <table id="reports-table">
      <thead>
        <tr>
          <th scope="col">Client</th>
          <th scope="col">Platform</th>
          <th scope="col">Status</th>
          <th scope="col">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['client_name']) ?></td>
            <td><?= h(platforms()[$r['platform']][0] ?? $r['platform']) ?></td>
            <td><span class="status"><?= h($r['status']) ?></span></td>
            <td><?= (int)$r['total'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="4" class="empty">No publication records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
