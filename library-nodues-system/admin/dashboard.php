<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role(['admin']);

$counts = db()->query(
    "SELECT current_stage, COUNT(*) c FROM applications GROUP BY current_stage"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$totalApps   = array_sum($counts);
$totalUsers  = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$avgDays     = db()->query(
    "SELECT ROUND(AVG(DATEDIFF(certificate_issued_at, created_at)),1) FROM applications WHERE status='approved'"
)->fetchColumn();

$recent = db()->query(
    "SELECT a.ticket_no, a.id, a.status, a.current_stage, a.created_at,
            COALESCE(u.full_name, a.applicant_name) AS display_name
     FROM applications a
     LEFT JOIN users u ON u.id = a.applicant_id
     ORDER BY a.created_at DESC LIMIT 15"
)->fetchAll();

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="row mb-4 g-3">
  <div class="col-md-3"><div class="card stat-card bg-primary text-white p-3"><div class="stat-number"><?= $totalApps ?></div><div>Total Applications</div></div></div>
  <div class="col-md-3"><div class="card stat-card bg-info text-white p-3"><div class="stat-number"><?= $totalUsers ?></div><div>Registered Staff</div></div></div>
  <div class="col-md-3"><div class="card stat-card bg-success text-white p-3"><div class="stat-number"><?= $counts['completed'] ?? 0 ?></div><div>Completed</div></div></div>
  <div class="col-md-3"><div class="card stat-card bg-secondary text-white p-3"><div class="stat-number"><?= $avgDays ?? '—' ?></div><div>Avg. Days to Clear</div></div></div>
</div>

<div class="row mb-4 g-3">
  <div class="col-md-4">
    <div class="card p-3">
      <h6>By Stage</h6>
      <?php foreach (['submitted','frontdesk','eresources','librarian','completed','rejected'] as $s): ?>
        <div class="d-flex justify-content-between border-bottom py-1">
          <span><?= h(stage_label($s)) ?></span><strong><?= $counts[$s] ?? 0 ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card p-3">
      <h6 class="mb-2">Manage</h6>
      <div class="d-flex flex-wrap gap-2">
        <a href="users.php" class="btn btn-outline-primary btn-sm">Manage Users &amp; Roles</a>
        <a href="departments.php" class="btn btn-outline-primary btn-sm">Manage Departments</a>
        <a href="reports.php" class="btn btn-outline-primary btn-sm">Reports</a>
        <a href="audit_log.php" class="btn btn-outline-secondary btn-sm">Audit Log</a>
      </div>
    </div>
  </div>
</div>

<h6>Recent Applications</h6>
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead><tr><th>Ticket</th><th>Applicant</th><th>Stage</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= h($r['ticket_no']) ?></td>
          <td><?= h($r['display_name']) ?></td>
          <td><?= h(stage_label($r['current_stage'])) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td><?= h(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
          <td>
            <a href="../edit_application.php?id=<?= $r['id'] ?>&back=../admin/dashboard.php"
               class="btn btn-outline-secondary btn-sm py-0">✏ Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
        <tr><td colspan="6" class="text-center text-muted py-3">No applications yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
