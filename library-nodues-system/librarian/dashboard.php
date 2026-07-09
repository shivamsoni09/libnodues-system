<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$staff = require_role(['librarian','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $appId  = (int) $_POST['id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        add_remark($appId, 'librarian', trim($_POST['remark'] ?? 'Approved by Librarian'), $staff['id']);
        db()->prepare(
            "UPDATE applications SET current_stage='completed', status='approved',
             certificate_issued_at = NOW() WHERE id=?"
        )->execute([$appId]);
        log_workflow($appId, 'librarian', 'completed', 'approved', $staff['id']);
        flash_set('success', 'Application approved. Returned to Front Desk for certificate printing.');
    } elseif ($action === 'reject') {
        add_remark($appId, 'librarian', trim($_POST['remark'] ?? 'Rejected by Librarian'), $staff['id']);
        db()->prepare("UPDATE applications SET current_stage='rejected', status='rejected' WHERE id=?")->execute([$appId]);
        log_workflow($appId, 'librarian', 'rejected', 'rejected', $staff['id']);
        flash_set('warning', 'Application rejected.');
    }
    header('Location: dashboard.php');
    exit;
}

$apps = db()->query(
    "SELECT a.*,
            COALESCE(u.full_name, a.applicant_name) AS display_name,
            COALESCE(u.library_card_no, a.applicant_library_card) AS library_card_no,
            dep.name AS dept_name,
            des.name AS desig_name
     FROM applications a
     LEFT JOIN users        u   ON u.id   = a.applicant_id
     LEFT JOIN departments  dep ON dep.id = a.department_id
     LEFT JOIN designations des ON des.id = a.designation_id
     WHERE a.current_stage = 'librarian'
     ORDER BY a.created_at ASC"
)->fetchAll();

$remarksStmt = db()->prepare(
    'SELECT r.*, us.full_name AS author FROM remarks r
     LEFT JOIN users us ON us.id = r.created_by
     WHERE application_id = ? ORDER BY created_at ASC'
);

$page_title = 'Librarian Review';
require __DIR__ . '/../includes/header.php';
?>
<h5 class="mb-3">Applications Awaiting Approval <span class="badge bg-primary"><?= count($apps) ?></span></h5>

<?php foreach ($apps as $a): $remarksStmt->execute([$a['id']]); $remarks = $remarksStmt->fetchAll(); ?>
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-1">
      <div>
        <h6 class="mb-0">
          <?= h($a['ticket_no']) ?> — <?= h($a['display_name']) ?>
          <?php if (!$a['applicant_id']): ?><span class="badge bg-info text-dark ms-1">Walk-in</span><?php endif; ?>
        </h6>
        <div class="text-muted small">
          <?= $a['desig_name'] ? h($a['desig_name']) . ', ' : '' ?><?= h($a['dept_name'] ?? '—') ?>
          <?php if ($a['applicant_email']): ?> &middot; <?= h($a['applicant_email']) ?><?php endif; ?>
          <?php if ($a['relieving_date']): ?> &middot; Relieving: <?= h(date('d M Y', strtotime($a['relieving_date']))) ?><?php endif; ?>
        </div>
      </div>
      <div class="d-flex flex-column align-items-end gap-1">
        <?= status_badge($a['status']) ?>
        <a href="../edit_application.php?id=<?= $a['id'] ?>&back=../librarian/dashboard.php"
           class="btn btn-outline-secondary" style="font-size:12px;padding:2px 8px">✏ Edit Details</a>
      </div>
    </div>

    <p class="small text-muted mb-1">
      Reason: <?= h($a['reason'] ?: '—') ?> &middot;
      Koha: <?= $a['koha_clear'] ? '<span class="text-success fw-bold">✓ Cleared</span>' : '<span class="text-danger">Not cleared</span>' ?>
    </p>

    <?php if ($remarks): ?>
      <ul class="small text-muted mb-2">
        <?php foreach ($remarks as $r): ?>
          <li><strong><?= h(stage_label($r['stage'])) ?></strong> — <?= h($r['remark']) ?>
            <em class="ms-1">(<?= h($r['author'] ?? 'system') ?>)</em></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" class="row g-2 align-items-end mt-1">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= $a['id'] ?>">
      <div class="col-md-8">
        <input name="remark" class="form-control form-control-sm" placeholder="Approval remark / digital signature note">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button name="action" value="approve" class="btn btn-sm btn-success">✓ Approve &amp; Sign</button>
        <button name="action" value="reject" class="btn btn-sm btn-outline-danger"
                onclick="return confirm('Reject this application?')">Reject</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$apps): ?><div class="card"><div class="card-body text-center text-muted py-4">Nothing awaiting approval.</div></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
