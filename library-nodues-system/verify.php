<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$ticket = trim($_GET['ticket'] ?? '');
$app = null;
if ($ticket !== '') {
    $stmt = db()->prepare(
        'SELECT a.ticket_no, a.status, a.certificate_issued_at, u.full_name
         FROM applications a JOIN users u ON u.id = a.applicant_id WHERE a.ticket_no = ?'
    );
    $stmt->execute([$ticket]);
    $app = $stmt->fetch();
}
$page_title = 'Verify Certificate';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card mt-4">
      <div class="card-body p-4 text-center">
        <h5 class="mb-3">Certificate Verification</h5>
        <form method="get" class="mb-3">
          <div class="input-group">
            <input name="ticket" class="form-control" placeholder="Ticket number (e.g. ND-2026-000001)" value="<?= h($ticket) ?>">
            <button class="btn btn-primary">Verify</button>
          </div>
        </form>
        <?php if ($ticket !== ''): ?>
          <?php if ($app && $app['status'] === 'approved'): ?>
            <div class="alert alert-success">
              ✓ Valid — <?= h($app['full_name']) ?>'s No Dues certificate (<?= h($app['ticket_no']) ?>)
              issued <?= h(date('d M Y', strtotime($app['certificate_issued_at']))) ?>.
            </div>
          <?php else: ?>
            <div class="alert alert-danger">No valid certificate found for that ticket number.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
