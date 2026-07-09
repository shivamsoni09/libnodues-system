<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/koha_api.php';
$staff = require_role(['frontdesk','admin']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT a.*,
            COALESCE(u.full_name, a.applicant_name) AS display_name,
            COALESCE(u.library_card_no, a.applicant_library_card) AS library_card_no
     FROM applications a
     LEFT JOIN users u ON u.id = a.applicant_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$app = $stmt->fetch();
if (!$app) { flash_set('danger', 'Application not found.'); header('Location: dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'check_koha') {
        $koha = new KohaApi();
        $result = $koha->checkPatronDues($app['library_card_no'] ?: 'UNKNOWN');
        db()->prepare(
            'UPDATE applications SET koha_checked=1, koha_books_issued=?, koha_fine_amount=?,
             koha_lost_items=?, koha_account_status=?, koha_clear=?, koha_checked_at=NOW() WHERE id=?'
        )->execute([
            $result['books_issued'], $result['fine_amount'], $result['lost_items'],
            $result['account_status'], $result['clear'] ? 1 : 0, $id,
        ]);
        flash_set($result['clear'] ? 'success' : 'warning',
            $result['error'] ?? ($result['clear'] ? 'Circulation cleared.' : 'Outstanding issue found — cannot forward yet.'));
        header('Location: check_koha.php?id=' . $id);
        exit;
    }

    if ($action === 'forward') {
        $fresh = db()->prepare('SELECT koha_clear FROM applications WHERE id=?');
        $fresh->execute([$id]);
        $clear = $fresh->fetchColumn();
        if (!$clear) {
            flash_set('danger', 'Cannot forward: circulation is not clear yet.');
        } else {
            add_remark($id, 'frontdesk', trim($_POST['remark'] ?? ''), $staff['id']);
            db()->prepare("UPDATE applications SET current_stage='eresources', status='in_progress' WHERE id=?")->execute([$id]);
            log_workflow($id, 'frontdesk', 'eresources', 'forwarded', $staff['id']);
            flash_set('success', 'Forwarded to E-Resources.');
        }
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'reject') {
        add_remark($id, 'frontdesk', trim($_POST['remark'] ?? 'Rejected at Front Desk'), $staff['id']);
        db()->prepare("UPDATE applications SET current_stage='rejected', status='rejected' WHERE id=?")->execute([$id]);
        log_workflow($id, 'frontdesk', 'rejected', 'rejected', $staff['id']);
        flash_set('warning', 'Application rejected.');
        header('Location: dashboard.php');
        exit;
    }
}

$page_title = 'Ticket ' . $app['ticket_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-body p-4">
        <h5 class="mb-1">Ticket <?= h($app['ticket_no']) ?> — <?= h($app['display_name']) ?>
          <?php if (!$app['applicant_id']): ?>
            <span class="badge bg-info text-dark ms-1">Walk-in / Public Form</span>
          <?php endif; ?>
        </h5>
        <div class="row text-muted small mt-2 g-2">
          <?php if ($app['applicant_email']): ?>
            <div class="col-auto">📧 <?= h($app['applicant_email']) ?></div>
          <?php endif; ?>
          <?php if ($app['applicant_phone']): ?>
            <div class="col-auto">📞 <?= h($app['applicant_phone']) ?></div>
          <?php endif; ?>
          <?php if ($app['joining_date']): ?>
            <div class="col-auto">📅 Joined: <?= h(date('d M Y', strtotime($app['joining_date']))) ?></div>
          <?php endif; ?>
          <?php if ($app['relieving_date']): ?>
            <div class="col-auto">📅 Relieving: <?= h(date('d M Y', strtotime($app['relieving_date']))) ?></div>
          <?php endif; ?>
          <?php if ($app['reason']): ?>
            <div class="col-auto">Reason: <?= h($app['reason']) ?></div>
          <?php endif; ?>
          <?php if ($app['library_card_no']): ?>
            <div class="col-auto">Card: <?= h($app['library_card_no']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-4">
        <h6 class="mb-3">Koha Circulation Check</h6>
        <div class="mb-4">
          <?php if (!$app['koha_checked']): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="check_koha">
              <button class="btn btn-primary">Check Koha</button>
              <?php if (!$app['library_card_no'] && !$app['applicant_id']): ?>
                <p class="text-warning small mt-2">⚠ No library card number on file for this walk-in applicant. Koha check will use simulated data.</p>
              <?php endif; ?>
            </form>
          <?php else: ?>
            <div class="alert <?= $app['koha_clear'] ? 'alert-success' : 'alert-danger' ?>">
              <strong><?= $app['koha_clear'] ? '✓ Circulation Cleared' : '✗ Outstanding Issue' ?></strong>
              <ul class="mb-0 mt-2">
                <li>Books issued: <?= h($app['koha_books_issued']) ?></li>
                <li>Outstanding fine: ₹<?= h(number_format((float)$app['koha_fine_amount'], 2)) ?></li>
                <li>Lost items: <?= h($app['koha_lost_items']) ?></li>
                <li>Account status: <?= h($app['koha_account_status']) ?></li>
              </ul>
              <p class="small text-muted mb-0 mt-2">
                Checked <?= h(date('d M Y H:i', strtotime($app['koha_checked_at']))) ?>
                <?php if (!KOHA_API_LIVE): ?> &middot; <em>simulated data</em><?php endif; ?>
              </p>
            </div>
            <form method="post" class="mb-2">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="check_koha">
              <button class="btn btn-outline-secondary btn-sm">Re-check Koha</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($app['koha_checked']): ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Remark (optional)</label>
            <textarea name="remark" class="form-control" rows="2"></textarea>
          </div>
          <button type="submit" name="action" value="forward" class="btn btn-success" <?= $app['koha_clear'] ? '' : 'disabled' ?>>
            Forward to E-Resources
          </button>
          <button type="submit" name="action" value="reject" class="btn btn-outline-danger ms-2"
                  onclick="return confirm('Reject this application?')">Reject</button>
        </form>
        <?php endif; ?>

        <div class="mt-3 d-flex gap-2">
          <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">← Back to Front Desk</a>
          <a href="../edit_application.php?id=<?= $app['id'] ?>&back=../frontdesk/check_koha.php%3Fid=<?= $app['id'] ?>"
             class="btn btn-outline-primary btn-sm">✏ Edit Applicant Details</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
