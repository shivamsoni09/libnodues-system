<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$staff = require_role(['frontdesk','admin']);

$apps = db()->query(
    "SELECT a.*,
            COALESCE(u.full_name, a.applicant_name) AS display_name,
            COALESCE(u.library_card_no, a.applicant_library_card) AS library_card_no,
            dep.name AS dept_name
     FROM applications a
     LEFT JOIN users       u   ON u.id   = a.applicant_id
     LEFT JOIN departments dep ON dep.id = a.department_id
     WHERE a.current_stage = 'frontdesk' AND a.status IN ('pending','in_progress')
     ORDER BY a.created_at ASC"
)->fetchAll();

$page_title = 'Front Desk';
require __DIR__ . '/../includes/header.php';
?>
<h5 class="mb-3">Pending Requests <span class="badge bg-primary"><?= count($apps) ?></span></h5>
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th>Ticket No</th><th>Applicant</th><th>Contact</th><th>Department</th><th>Reason</th>
          <th>Koha Check</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$apps): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No pending requests at Front Desk.</td></tr>
      <?php endif; ?>
      <?php foreach ($apps as $a): ?>
        <tr>
          <td><?= h($a['ticket_no']) ?></td>
          <td>
            <?= h($a['display_name']) ?>
            <?php if (!$a['applicant_id']): ?>
              <span class="badge bg-info text-dark ms-1">Walk-in</span>
            <?php endif; ?>
          </td>
          <td class="small text-muted">
            <?= $a['applicant_email'] ? h($a['applicant_email']) : '' ?>
            <?= $a['applicant_phone'] ? '<br>' . h($a['applicant_phone']) : '' ?>
          </td>
          <td class="small"><?= h($a['dept_name'] ?? '—') ?></td>
          <td><?= h($a['reason']) ?></td>
          <td>
            <?php if (!$a['koha_checked']): ?>
              <span class="text-muted">Not checked</span>
            <?php elseif ($a['koha_clear']): ?>
              <span class="text-success fw-bold">✓ Cleared</span>
            <?php else: ?>
              <span class="text-danger fw-bold">✗ Outstanding</span>
            <?php endif; ?>
          </td>
          <td><?= status_badge($a['status']) ?></td>
          <td>
            <a href="check_koha.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
