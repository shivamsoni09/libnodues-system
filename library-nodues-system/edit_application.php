<?php
/**
 * Edit application details — for staff (all roles).
 * Accessible from any stage's dashboard via ?id=X[&back=URL]
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$staff = require_role(['frontdesk', 'eresources', 'librarian', 'admin']);

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$back = $_GET['back'] ?? $_POST['back'] ?? dashboard_url_for_role($staff['role_key']);

$stmt = db()->prepare(
    "SELECT a.*,
            COALESCE(u.full_name, a.applicant_name) AS display_name
     FROM applications a
     LEFT JOIN users u ON u.id = a.applicant_id
     WHERE a.id = ?"
);
$stmt->execute([$id]);
$app = $stmt->fetch();
if (!$app) {
    flash_set('danger', 'Application not found.');
    header('Location: ' . $back);
    exit;
}

$departments  = db()->query('SELECT id, name FROM departments  WHERE active=1 ORDER BY name')->fetchAll();
$designations = db()->query('SELECT id, name FROM designations WHERE active=1 ORDER BY name')->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name      = trim($_POST['applicant_name']  ?? '');
    $email     = trim($_POST['applicant_email'] ?? '');
    $phone     = trim($_POST['applicant_phone'] ?? '');
    $deptId    = $_POST['department_id']   ?: null;
    $desigId   = $_POST['designation_id']  ?: null;
    $joining   = $_POST['joining_date']    ?: null;
    $relieving = $_POST['relieving_date']  ?: null;
    $reason    = trim($_POST['reason']         ?? '');

    if ($name === '') {
        $error = 'Applicant name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($joining && $relieving && $joining > $relieving) {
        $error = 'Joining date must be before relieving date.';
    } else {
        db()->prepare(
            'UPDATE applications
             SET applicant_name=?, applicant_email=?, applicant_phone=?,
                 department_id=?, designation_id=?,
                 joining_date=?, relieving_date=?, reason=?
             WHERE id=?'
        )->execute([$name, $email ?: null, $phone ?: null,
                    $deptId, $desigId, $joining, $relieving, $reason ?: null, $id]);

        log_workflow($id, null, $app['current_stage'], 'details_edited', $staff['id']);
        flash_set('success', 'Application details updated.');
        header('Location: ' . $back);
        exit;
    }

    // Re-populate $app with posted values on error
    $app['applicant_name']  = $name;
    $app['applicant_email'] = $email;
    $app['applicant_phone'] = $phone;
    $app['department_id']   = $deptId;
    $app['designation_id']  = $desigId;
    $app['joining_date']    = $joining;
    $app['relieving_date']  = $relieving;
    $app['reason']          = $reason;
}

$page_title = 'Edit Application — ' . $app['ticket_no'];
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-7">
    <div class="d-flex align-items-center mb-3 gap-3">
      <a href="<?= h($back) ?>" class="btn btn-outline-secondary btn-sm">← Back</a>
      <h5 class="mb-0">Edit Application — <span class="text-primary"><?= h($app['ticket_no']) ?></span></h5>
      <span class="ms-auto"><?= status_badge($app['status']) ?> <span class="badge bg-secondary"><?= h(stage_label($app['current_stage'])) ?></span></span>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body p-4">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="back" value="<?= h($back) ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input name="applicant_name" class="form-control"
                     value="<?= h($app['applicant_name'] ?? $app['display_name'] ?? '') ?>" required>
              <?php if ($app['applicant_id']): ?>
                <div class="form-text text-muted">Linked account: <?= h($app['display_name']) ?></div>
              <?php endif; ?>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="applicant_email" class="form-control"
                     value="<?= h($app['applicant_email'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone Number</label>
              <input name="applicant_phone" class="form-control"
                     value="<?= h($app['applicant_phone'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Designation</label>
              <select name="designation_id" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($designations as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($app['designation_id'] == $d['id']) ? 'selected' : '' ?>>
                    <?= h($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Department</label>
              <select name="department_id" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($app['department_id'] == $d['id']) ? 'selected' : '' ?>>
                    <?= h($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Date of Joining</label>
              <input type="date" name="joining_date" class="form-control"
                     value="<?= h($app['joining_date'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Date of Relieving</label>
              <input type="date" name="relieving_date" class="form-control"
                     value="<?= h($app['relieving_date'] ?? '') ?>">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Reason for No Dues</label>
              <select name="reason" class="form-select">
                <option value="">— Select reason —</option>
                <?php foreach (['Course completion / graduation','Transfer to another institution','Resignation','Semester clearance','Scholarship requirement','Other'] as $opt): ?>
                  <option <?= ($app['reason'] === $opt) ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary px-4">Save Changes</button>
            <a href="<?= h($back) ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
