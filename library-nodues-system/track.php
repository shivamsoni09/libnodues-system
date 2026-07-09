<?php
/**
 * Public ticket status tracker — no login required.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$ticket = trim($_GET['ticket'] ?? $_POST['ticket'] ?? '');
$app    = null;
$remarks = [];

if ($ticket !== '') {
    $stmt = db()->prepare(
        "SELECT a.*,
                COALESCE(u.full_name, a.applicant_name) AS display_name,
                dep.name AS dept_name,
                des.name AS desig_name
         FROM applications a
         LEFT JOIN users      u   ON u.id   = a.applicant_id
         LEFT JOIN departments dep ON dep.id = a.department_id
         LEFT JOIN designations des ON des.id = a.designation_id
         WHERE a.ticket_no = ?"
    );
    $stmt->execute([strtoupper($ticket)]);
    $app = $stmt->fetch();

    if ($app) {
        $rStmt = db()->prepare(
            "SELECT r.stage, r.remark, r.created_at, u.full_name AS author
             FROM remarks r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.application_id = ?
             ORDER BY r.created_at ASC"
        );
        $rStmt->execute([$app['id']]);
        $remarks = $rStmt->fetchAll();
    }
}

$stage_steps = [
    'frontdesk'  => ['Front Desk', 'Verifying library records'],
    'eresources' => ['E-Resources', 'Checking e-resource access'],
    'librarian'  => ['Librarian', 'Awaiting final approval'],
    'completed'  => ['Completed', 'No Dues certificate issued'],
    'rejected'   => ['Rejected', 'Application rejected'],
];
$ordered_stages = ['frontdesk', 'eresources', 'librarian', 'completed'];

$page_title = 'Track Application';
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
    <div class="ms-auto d-flex gap-2">
      <a href="apply.php" class="btn btn-outline-light btn-sm">Apply for No Dues</a>
      <a href="login.php" class="btn btn-outline-light btn-sm">Staff Login</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">

      <div class="card mb-4">
        <div class="card-body p-4">
          <h5 class="mb-1">Track Your Application</h5>
          <p class="text-muted small mb-3">Enter the ticket number you received when you submitted your form.</p>
          <form method="get" class="d-flex gap-2">
            <input name="ticket" class="form-control" value="<?= h($ticket) ?>"
                   placeholder="e.g. ND-2026-000001" required autofocus>
            <button class="btn btn-primary px-4">Track</button>
          </form>
        </div>
      </div>

      <?php if ($ticket !== '' && !$app): ?>
        <div class="alert alert-warning">
          No application found for ticket <strong><?= h($ticket) ?></strong>. Please check the number and try again.
        </div>
      <?php endif; ?>

      <?php if ($app): ?>
        <div class="card mb-3">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="mb-0"><?= h($app['ticket_no']) ?></h5>
                <div class="text-muted small"><?= h($app['display_name']) ?></div>
                <?php if ($app['dept_name']): ?>
                  <div class="text-muted small"><?= h($app['desig_name'] ?? '') ?><?= $app['desig_name'] && $app['dept_name'] ? ', ' : '' ?><?= h($app['dept_name']) ?></div>
                <?php endif; ?>
              </div>
              <div>
                <?= status_badge($app['status']) ?>
              </div>
            </div>

            <?php if ($app['current_stage'] !== 'rejected'): ?>
            <div class="d-flex gap-0 mb-3">
              <?php foreach ($ordered_stages as $i => $s):
                $isActive  = $app['current_stage'] === $s;
                $isPast    = ($app['status'] === 'approved' && $s === 'completed') ||
                             (array_search($s, $ordered_stages) < array_search($app['current_stage'], $ordered_stages));
                $isLast    = $i === count($ordered_stages) - 1;
              ?>
              <div class="flex-fill text-center" style="position:relative">
                <div class="d-flex flex-column align-items-center">
                  <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                       style="width:36px;height:36px;font-size:13px;
                              background:<?= $isPast ? '#198754' : ($isActive ? '#0d6efd' : '#dee2e6') ?>;
                              color:<?= ($isPast || $isActive) ? '#fff' : '#6c757d' ?>">
                    <?= $isPast ? '✓' : ($i + 1) ?>
                  </div>
                  <div class="small mt-1" style="font-size:11px;color:<?= $isActive ? '#0d6efd' : ($isPast ? '#198754' : '#6c757d') ?>">
                    <?= h($stage_steps[$s][0]) ?>
                  </div>
                </div>
                <?php if (!$isLast): ?>
                <div style="position:absolute;top:18px;left:50%;width:100%;height:2px;background:<?= $isPast ? '#198754' : '#dee2e6' ?>"></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-3 small text-muted">
              <span>📋 Reason: <?= h($app['reason'] ?: '—') ?></span>
              <?php if ($app['joining_date']): ?><span>📅 Joined: <?= h(date('d M Y', strtotime($app['joining_date']))) ?></span><?php endif; ?>
              <?php if ($app['relieving_date']): ?><span>📅 Relieving: <?= h(date('d M Y', strtotime($app['relieving_date']))) ?></span><?php endif; ?>
              <span>🕐 Submitted: <?= h(date('d M Y', strtotime($app['created_at']))) ?></span>
            </div>

            <?php if ($app['current_stage'] === 'completed' && $app['status'] === 'approved'): ?>
            <div class="alert alert-success mt-3 mb-0">
              <strong>✅ No Dues Cleared!</strong> Your certificate has been issued. Please collect it from the library front desk.
            </div>
            <?php elseif ($app['current_stage'] === 'rejected'): ?>
            <div class="alert alert-danger mt-3 mb-0">
              <strong>❌ Application Rejected.</strong> Please contact the library for more information.
            </div>
            <?php else: ?>
            <div class="alert alert-info mt-3 mb-0">
              <strong>⏳ <?= h($stage_steps[$app['current_stage']][0]) ?></strong> — <?= h($stage_steps[$app['current_stage']][1]) ?>. Please allow 1–2 working days.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($remarks): ?>
        <div class="card">
          <div class="card-header"><strong>Activity Log</strong></div>
          <ul class="list-group list-group-flush">
            <?php foreach ($remarks as $r): ?>
            <li class="list-group-item small">
              <span class="badge bg-secondary me-2"><?= h(stage_label($r['stage'])) ?></span>
              <?= h($r['remark']) ?>
              <span class="text-muted float-end"><?= h(date('d M Y', strtotime($r['created_at']))) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<footer class="text-center text-muted small py-4 mt-5">
  <?= h(APP_NAME) ?> &middot; <?= date('Y') ?>
</footer>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
