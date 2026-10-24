<?php
declare(strict_types=1);

/**
 * @var array $jobs
 * @var array $queuedJobs
 */
?>
<div class="hero small">
  <div>
    <span class="eyebrow">Workflow Dispatcher</span>
    <h1>Publishing Operations & Queue</h1>
    <p>Assisted manual actions and automated scheduled pipelines.</p>
  </div>
  <div class="actions">
    <form method="post" action="index.php?page=run-queue" class="form-inline">
      <?= csrf_field() ?>
      <button type="submit" class="btn">
        <?= Icons::svg('zap', 15) ?>
        <span>Process Automated Queue Now</span>
      </button>
    </form>
  </div>
</div>

<div class="card">
  <div class="cardhead">
    <div>
      <h2>Assisted / Manual Publishing Jobs</h2>
      <p>For channels requiring manual copy-paste or browser verification.</p>
    </div>
    <div class="table-search-bar">
      <input type="search" class="table-search-input" placeholder="Search manual jobs..." data-table-filter="manual-jobs-table" aria-label="Search manual jobs">
    </div>
  </div>
  <div class="tablewrap">
    <table id="manual-jobs-table">
      <thead>
        <tr>
          <th scope="col">Campaign & Content</th>
          <th scope="col">Client</th>
          <th scope="col">Platform</th>
          <th scope="col">Scheduled</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $j): ?>
          <?php
            $pName = platforms()[$j['platform']][0] ?? $j['platform'];
            $caption = !empty($j['message']) ? h($j['message']) : '<span class="text-muted">No caption provided</span>';
            $rawMsg = $j['message'] ?? '';
          ?>
          <tr>
            <td>
              <b><?= h($j['campaign_name']) ?></b>
              <div class="caption-preview">
                <?= $caption ?>
                <button type="button" class="copy-btn" aria-label="Copy caption to clipboard" data-copy-text="<?= h($rawMsg) ?>">Copy</button>
              </div>
              <?php if (!empty($j['media_path'])): ?>
                <div class="media-preview-box">
                  <a href="<?= h($j['media_path']) ?>" target="_blank" rel="noopener">
                    <img src="<?= h($j['media_path']) ?>" class="media-thumb" alt="Job media attachment">
                  </a>
                  <span class="media-hint">Image Attached (Click to view full size)</span>
                </div>
              <?php endif; ?>
              <?php if (!empty($j['link_url'])): ?>
                <div class="mt-4">
                  <a href="<?= h($j['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="job-link">🔗 <?= h($j['link_url']) ?></a>
                </div>
              <?php endif; ?>
            </td>
            <td><?= h($j['client_name']) ?></td>
            <td><span class="pill"><?= h($pName) ?></span></td>
            <td><?= h($j['scheduled_for']) ?></td>
            <td>
              <form method="post" action="index.php?page=job-complete" class="form-inline" data-confirm="Mark this job as published externally on <?= h($pName) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <button class="smallbtn" type="submit">Mark Published</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($jobs)): ?>
          <tr class="empty-row"><td colspan="5" class="empty">No manual jobs pending.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-20">
  <div class="cardhead">
    <div>
      <h2>Automated Scheduled Pipeline (Next in Queue)</h2>
      <p>Automated jobs that publish via the CLI runner or the button above.</p>
    </div>
    <div class="table-search-bar">
      <input type="search" class="table-search-input" placeholder="Search automated pipeline..." data-table-filter="automated-jobs-table" aria-label="Search automated queue">
    </div>
  </div>
  <div class="tablewrap">
    <table id="automated-jobs-table">
      <thead>
        <tr>
          <th scope="col">Campaign</th>
          <th scope="col">Client</th>
          <th scope="col">Platform</th>
          <th scope="col">Schedule</th>
          <th scope="col">Execution</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($queuedJobs as $qj): ?>
          <tr>
            <td><b><?= h($qj['campaign_name']) ?></b></td>
            <td><?= h($qj['client_name']) ?></td>
            <td><span class="pill"><?= h(platforms()[$qj['platform']][0] ?? $qj['platform']) ?></span></td>
            <td><?= h($qj['scheduled_for']) ?></td>
            <td><?= h($qj['execution_mode']) ?></td>
            <td><span class="status"><?= h($qj['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($queuedJobs)): ?>
          <tr class="empty-row"><td colspan="6" class="empty">No automated jobs currently queued.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
