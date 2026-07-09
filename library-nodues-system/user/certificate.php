<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_role(['user']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM applications WHERE id = ? AND applicant_id = ? AND status = "approved"');
$stmt->execute([$id, $user['id']]);
$app = $stmt->fetch();
if (!$app) {
    flash_set('danger', 'Certificate not found or not yet approved.');
    header('Location: dashboard.php');
    exit;
}

$qrData = APP_URL . '/verify.php?ticket=' . urlencode($app['ticket_no']);
$page_title = 'No Dues Certificate';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-end mb-2 d-print-none">
  <button onclick="window.print()" class="btn btn-primary">🖨️ Print / Save as PDF</button>
</div>
<div class="card">
  <div class="card-body p-5">
    <div class="certificate-box text-center">
      <h3 class="mb-1">LIBRARY NO DUES CERTIFICATE</h3>
      <p class="text-muted mb-4">This is to certify that circulation clearance has been granted</p>

      <table class="table table-borderless w-75 mx-auto text-start">
        <tr><th>Ticket No.</th><td><?= h($app['ticket_no']) ?></td></tr>
        <tr><th>Name</th><td><?= h($user['full_name']) ?></td></tr>
        <tr><th>Library Card No.</th><td><?= h($user['library_card_no']) ?></td></tr>
        <tr><th>Reason</th><td><?= h($app['reason']) ?></td></tr>
        <tr><th>Circulation Status</th><td>✓ Cleared</td></tr>
        <tr><th>Issued On</th><td><?= h(date('d M Y', strtotime($app['certificate_issued_at'] ?? $app['updated_at']))) ?></td></tr>
      </table>

      <div class="mt-4 d-flex justify-content-between align-items-end px-5">
        <div class="text-start">
          <div style="border-top:1px solid #333; width:200px; margin-top:2.5rem;"></div>
          <small>Front Desk / Circulation</small>
        </div>
        <div class="text-center">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($qrData) ?>"
               alt="QR" width="100" height="100"
               onerror="this.style.display='none'">
          <div class="small text-muted">Scan to verify</div>
        </div>
        <div class="text-start">
          <div style="border-top:1px solid #333; width:200px; margin-top:2.5rem;"></div>
          <small>Librarian (Digitally Approved)</small>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
