<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_role(['user']);

$stmt = db()->prepare('SELECT * FROM applications WHERE applicant_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$apps = $stmt->fetchAll();

$page_title = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card stat-card bg-primary text-white p-3">
      <div class="stat-number"><?= count($apps) ?></div>
      <div>Total Applications</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card bg-success text-white p-3">
      <div class="stat-number"><?= count(array_filter($apps, fn($a) => $a['status'] === 'approved')) ?></div>
      <div>Approved</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card bg-warning text-dark p-3">
      <div class="stat-number"><?= count(array_filter($apps, fn($a) => in_array($a['status'], ['pending','in_progress']))) ?></div>
      <div>In Progress</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card bg-danger text-white p-3">
      <div class="stat-number"><?= count(array_filter($apps, fn($a) => $a['status'] === 'rejected')) ?></div>
      <div>Rejected</div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">My Applications</h5>
  <a href="new_application.php" class="btn btn-primary">+ New No Dues Application</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr><th>Ticket No</th><th>Reason</th><th>Stage</th><th>Status</th><th>Submitted</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$apps): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No applications yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($apps as $a): ?>
        <tr>
          <td><?= h($a['ticket_no']) ?></td>
          <td><?= h($a['reason']) ?></td>
          <td><?= h(stage_label($a['current_stage'])) ?></td>
          <td><?= status_badge($a['status']) ?></td>
          <td><?= h(date('d M Y', strtotime($a['created_at']))) ?></td>
          <td>
            <?php if ($a['status'] === 'approved'): ?>
              <a href="certificate.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success">Certificate</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
