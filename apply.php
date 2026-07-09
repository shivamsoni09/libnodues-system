<?php
/**
 * Public No Dues application form — no login required.
 * Submits directly into the Front Desk queue.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Existing names are offered as <datalist> autocomplete suggestions only —
// the field stays a free-text box, this just helps avoid near-duplicate
// entries like "Comp Sci" vs "Computer Science".
$departments  = db()->query('SELECT name FROM departments WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$designations = db()->query('SELECT name FROM designations WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$ticket  = null;
$error   = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name        = trim($_POST['full_name']         ?? '');
    $email       = trim($_POST['email']             ?? '');
    $phone       = trim($_POST['phone']             ?? '');
    $cardNo      = trim($_POST['library_card_no']   ?? '');
    $deptText    = trim($_POST['department']        ?? '');
    $desigText   = trim($_POST['designation']       ?? '');
    $joining     = $_POST['joining_date']   ?: null;
    $relieving   = $_POST['relieving_date'] ?: null;
    $reason      = trim($_POST['reason']            ?? '');

    if ($name === '' || $email === '' || $phone === '' || $deptText === '' || $desigText === '' || !$joining || !$relieving) {
        $error = 'All fields except Library Card No. are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($joining > $relieving) {
        $error = 'Date of joining must be before date of relieving.';
    } else {
        // Free-text department/designation: match an existing entry
        // case-insensitively, or create a new one — keeps admin reports
        // and filters working without forcing the applicant to pick
        // from a dropdown.
        $deptId  = find_or_create_lookup('departments', $deptText);
        $desigId = find_or_create_lookup('designations', $desigText);
        $ticket = next_ticket_no();
        db()->prepare(
            'INSERT INTO applications
               (ticket_no, applicant_id, applicant_name, applicant_email, applicant_phone,
                applicant_library_card, joining_date, relieving_date,
                department_id, designation_id, reason, current_stage, status)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, "frontdesk", "pending")'
        )->execute([
            $ticket, $name, $email, $phone,
            $cardNo ?: null, $joining, $relieving,
            $deptId, $desigId, $reason ?: 'No Dues Required',
        ]);

        $appId = (int) db()->lastInsertId();
        log_workflow($appId, null, 'frontdesk', 'submitted', null);
        $success = true;
    }
}

$page_title = 'Apply for No Dues';
$root = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="apply.php">📚 <?= h(APP_NAME) ?></a>
    <div class="ms-auto">
      <a href="track.php" class="btn btn-outline-light btn-sm">Track Application</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4">
<?php if ($success): ?>
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
      <div class="card mt-4 border-success">
        <div class="card-body text-center p-5">
          <div class="display-4 mb-3">✅</div>
          <h4 class="text-success">Application Submitted!</h4>
          <p class="text-muted mb-1">Your ticket number is:</p>
          <h2 class="fw-bold text-primary mb-3"><?= h($ticket) ?></h2>
          <p class="text-muted small">Please note this number. Library staff will process your request and contact you at the email you provided.</p>
          <div class="d-flex gap-2 justify-content-center mt-2">
            <a href="track.php?ticket=<?= urlencode($ticket) ?>" class="btn btn-primary">Track Status</a>
            <a href="apply.php" class="btn btn-outline-secondary">Submit Another</a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
      <div class="card mt-3">
        <div class="card-body p-4">
          <h4 class="mb-1">No Dues Application Form</h4>
          <p class="text-muted small mb-4">Fill in your details below. Library staff will verify and process your request.</p>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input name="full_name" class="form-control" value="<?= h($_POST['full_name'] ?? '') ?>"
                       placeholder="As per ID card" required autofocus>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>"
                       placeholder="your@email.com" required>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                <input name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>"
                       placeholder="+91 9XXXXXXXXX" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Library Card No. <span class="text-muted fw-normal">(optional)</span></label>
                <input name="library_card_no" class="form-control" value="<?= h($_POST['library_card_no'] ?? '') ?>"
                       placeholder="As printed on your Koha library card">
                <div class="form-text">Providing this speeds up your Koha circulation check.</div>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Designation <span class="text-danger">*</span></label>
                <input name="designation" class="form-control" list="designation_options"
                       value="<?= h($_POST['designation'] ?? '') ?>"
                       placeholder="e.g. Student, Faculty, Staff" required>
                <datalist id="designation_options">
                  <?php foreach ($designations as $d): ?><option value="<?= h($d) ?>"><?php endforeach; ?>
                </datalist>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                <input name="department" class="form-control" list="department_options"
                       value="<?= h($_POST['department'] ?? '') ?>"
                       placeholder="e.g. Computer Science" required>
                <datalist id="department_options">
                  <?php foreach ($departments as $d): ?><option value="<?= h($d) ?>"><?php endforeach; ?>
                </datalist>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Date of Joining <span class="text-danger">*</span></label>
                <input type="date" name="joining_date" class="form-control"
                       value="<?= h($_POST['joining_date'] ?? '') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Date of Relieving <span class="text-danger">*</span></label>
                <input type="date" name="relieving_date" class="form-control"
                       value="<?= h($_POST['relieving_date'] ?? '') ?>" required>
              </div>

              <div class="col-md-12">
                <label class="form-label fw-semibold">Reason for No Dues</label>
                <select name="reason" class="form-select">
                  <option value="">— Select reason —</option>
                  <option <?= (($_POST['reason'] ?? '') === 'Course completion / graduation') ? 'selected' : '' ?>>Course completion / graduation</option>
                  <option <?= (($_POST['reason'] ?? '') === 'Transfer to another institution') ? 'selected' : '' ?>>Transfer to another institution</option>
                  <option <?= (($_POST['reason'] ?? '') === 'Resignation') ? 'selected' : '' ?>>Resignation</option>
                  <option <?= (($_POST['reason'] ?? '') === 'Semester clearance') ? 'selected' : '' ?>>Semester clearance</option>
                  <option <?= (($_POST['reason'] ?? '') === 'Scholarship requirement') ? 'selected' : '' ?>>Scholarship requirement</option>
                  <option <?= (($_POST['reason'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
              </div>
            </div>

            <div class="mt-4">
              <button class="btn btn-primary btn-lg px-5">Submit Application</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>

<footer class="text-center text-muted small py-4 mt-5">
  <?= h(APP_NAME) ?> &middot; <?= date('Y') ?>
</footer>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
