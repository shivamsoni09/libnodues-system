<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role(['admin']);

$logs = db()->query(
    "SELECT al.*, u.full_name FROM audit_log al LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Audit Log';
require __DIR__ . '/../includes/header.php';
?>
<h5 class="mb-3">Audit Log <small class="text-muted">(latest 300 entries)</small></h5>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= h(date('d M Y H:i:s', strtotime($l['created_at']))) ?></td>
          <td><?= h($l['full_name'] ?? 'system') ?></td>
          <td><?= h($l['action']) ?></td>
          <td><?= h($l['details']) ?></td>
          <td><?= h($l['ip_address']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
