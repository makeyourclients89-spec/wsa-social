<?php
declare(strict_types=1);

/**
 * @var array $clients
 */
?>
<div class="hero small">
  <div>
    <span class="eyebrow">Client Management</span>
    <h1>Client Workspaces</h1>
    <p>Organize social profiles, rate limits, and scheduled content by brand.</p>
  </div>
</div>

<div class="grid two">
  <form class="card" method="post" action="index.php?page=client-add">
    <?= csrf_field() ?>
    <h2>Add Client Workspace</h2>
    <label for="c_name">Client or Brand Name
      <input id="c_name" name="name" required placeholder="e.g. Acme Global Marketing">
    </label>
    <label for="c_tz">Primary Timezone
      <input id="c_tz" name="timezone" value="Asia/Kolkata" required placeholder="e.g. America/New_York or Asia/Kolkata">
    </label>
    <button type="submit">Create Workspace</button>
  </form>

  <div class="card">
    <div class="cardhead">
      <div>
        <h2>Active Workspaces</h2>
        <p>Manage access and publishing rights</p>
      </div>
      <div class="table-search-bar">
        <input type="search" class="table-search-input" placeholder="Search clients..." data-table-filter="clients-table" aria-label="Search clients">
      </div>
    </div>
    <div class="tablewrap">
      <table id="clients-table">
        <thead>
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Timezone</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
            <?php
              $isActive = (int)($c['is_active'] ?? 1) === 1;
              $statusBadge = $isActive 
                  ? '<span class="badge-active">Active</span>' 
                  : '<span class="badge-inactive">Inactive</span>';
              $toggleAction = $isActive ? 'Deactivate' : 'Activate';
            ?>
            <tr>
              <td><b><?= h($c['name']) ?></b></td>
              <td><?= h($c['timezone']) ?></td>
              <td><?= $statusBadge ?></td>
              <td>
                <div class="actions">
                  <form method="post" action="index.php?page=client-toggle" class="form-inline" data-confirm="Are you sure you want to <?= strtolower($toggleAction) ?> <?= h($c['name']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="smallbtn"><?= $toggleAction ?></button>
                  </form>
                  <form method="post" action="index.php?page=client-delete" class="form-inline" data-confirm="Warning: Deleting this client will delete all connected accounts, campaigns, and jobs! Proceed?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="smallbtn danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($clients)): ?>
            <tr class="empty-row"><td colspan="4" class="empty">No clients registered yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
