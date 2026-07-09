<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_role(['user']);

// Prevent duplicate open applications
$open = db()->prepare("SELECT id FROM applications WHERE applicant_id = ? AND status IN ('pending','in_progress')");
$open->execute([$user['id']]);
if ($open->fetch()) {
    flash_set('warning', 'You already have an application in progress. Please wait for it to be completed before submitting a new one.');
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
        $error = 'Please state the reason for your No Dues application.';
    } else {
        $ticket = next_ticket_no();
        $stmt = db()->prepare(
            'INSERT INTO applications (ticket_no, applicant_id, department_id, designation_id, reason, current_stage, status)
             VALUES (?, ?, ?, ?, ?, "frontdesk", "in_progress")'
        );
        $stmt->execute([$ticket, $user['id'], $user['department_id'], $user['designation_id'], $reason]);
        $appId = (int) db()->lastInsertId();
        log_workflow($appId, null, 'frontdesk', 'submitted', $user['id']);
        flash_set('success', "Application submitted. Your ticket number is $ticket.");
        header('Location: dashboard.php');
        exit;
    }
}
$page_title = 'New Application';
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card">
      <div class="card-body p-4">
        <h5 class="mb-3">New No Dues Application</h5>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <p class="text-muted small">
          Library card: <strong><?= h($user['library_card_no'] ?: 'not set — update in your profile') ?></strong>.
          Front Desk will check your Koha circulation record using this card number.
        </p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Reason for No Dues</label>
            <select name="reason" class="form-select" required>
              <option value="">— Select —</option>
              <option>Course completion</option>
              <option>Transfer / relieving</option>
              <option>Resignation</option>
              <option>Semester clearance</option>
              <option>Other</option>
            </select>
          </div>
          <button class="btn btn-primary">Submit Application</button>
          <a href="dashboard.php" class="btn btn-link">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
