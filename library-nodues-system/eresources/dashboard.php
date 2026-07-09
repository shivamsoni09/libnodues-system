<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$staff = require_role(['eresources','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $appId  = (int) $_POST['id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'forward') {
        add_remark($appId, 'eresources', trim($_POST['remark'] ?? ''), $staff['id']);
        db()->prepare("UPDATE applications SET current_stage='librarian', status='in_progress' WHERE id=?")->execute([$appId]);
        log_workflow($appId, 'eresources', 'librarian', 'forwarded', $staff['id']);
        flash_set('success', 'Forwarded to Librarian.');
    } elseif ($action === 'reject') {
        add_remark($appId, 'eresources', trim($_POST['remark'] ?? 'Rejected at E-Resources'), $staff['id']);
        db()->prepare("UPDATE applications SET current_stage='rejected', status='rejected' WHERE id=?")->execute([$appId]);
        log_workflow($appId, 'eresources', 'rejected', 'rejected', $staff['id']);
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
     WHERE a.current_stage = 'eresources'
     ORDER BY a.created_at ASC"
)->fetchAll();

$remarksStmt = db()->prepare(
    'SELECT r.*, us.full_name AS author FROM remarks r
     LEFT JOIN users us ON us.id = r.created_by
     WHERE application_id = ? ORDER BY created_at ASC'
);

$page_title = 'E-Resources';
require __DIR__ . '/../includes/header.php';
?>
<h5 class="mb-3">Pending from Circulation <span class="badge bg-primary"><?= count($apps) ?></span></h5>

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
        <div class="text-muted small">
          Reason: <?= h($a['reason'] ?: '—') ?> &middot;
          Koha: <?= $a['koha_clear'] ? '<span class="text-success">✓ Cleared</span>' : '<span class="text-warning">Not checked</span>' ?>
        </div>
      </div>
      <div class="d-flex flex-column align-items-end gap-1">
        <?= status_badge($a['status']) ?>
        <a href="../edit_application.php?id=<?= $a['id'] ?>&back=../eresources/dashboard.php"
           class="btn btn-xs btn-outline-secondary" style="font-size:12px;padding:2px 8px">✏ Edit Details</a>
      </div>
    </div>

    <?php if ($remarks): ?>
      <ul class="small text-muted mb-2 mt-2">
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
        <input name="remark" class="form-control form-control-sm" placeholder="Add a remark (e.g. e-resource access status)">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button name="action" value="forward" class="btn btn-sm btn-success">Forward to Librarian</button>
        <button name="action" value="reject" class="btn btn-sm btn-outline-danger"
                onclick="return confirm('Reject this application?')">Reject</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$apps): ?><div class="card"><div class="card-body text-center text-muted py-4">Nothing pending.</div></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
