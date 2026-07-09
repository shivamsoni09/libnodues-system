<?php
/** Expects $page_title to be set by the including page. */
$user = current_user();
$root = rel_path('');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title ?? APP_NAME) ?> · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= $root ?>assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $root ?>assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= $root ?><?= $user ? h(dashboard_url_for_role($user['role_key'])) : 'apply.php' ?>">
      📚 <?= h(APP_NAME) ?>
    </a>
    <?php if ($user): ?>
    <div class="d-flex align-items-center text-light">
      <span class="me-3">
        <?= h($user['full_name']) ?>
        <span class="badge bg-secondary ms-1"><?= h($user['role_name']) ?></span>
      </span>
      <a href="<?= $root ?>logout.php" class="btn btn-outline-light btn-sm">Log out</a>
    </div>
    <?php else: ?>
    <a href="<?= $root ?>login.php" class="btn btn-outline-light btn-sm">Staff Login</a>
    <?php endif; ?>
  </div>
</nav>
<?php if ($user): ?>
<div class="container-fluid px-4 mb-3">
  <div class="btn-group btn-group-sm">
    <?php
    $links = match ($user['role_key']) {
        'user' => [
            'dashboard.php' => 'Dashboard',
            'new_application.php' => 'New Application',
        ],
        'frontdesk' => [
            'dashboard.php' => 'Pending Requests',
            'completed.php' => 'Print Certificates',
        ],
        'eresources' => [
            'dashboard.php' => 'Pending from Circulation',
        ],
        'librarian' => [
            'dashboard.php' => 'Awaiting Approval',
        ],
        'admin' => [
            'dashboard.php' => 'Overview',
            'users.php' => 'Users',
            'departments.php' => 'Departments',
            'reports.php' => 'Reports',
            'audit_log.php' => 'Audit Log',
        ],
        default => [],
    };
    $currentFile = basename($_SERVER['PHP_SELF']);
    foreach ($links as $href => $label):
    ?>
      <a class="btn <?= $currentFile === $href ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= h($href) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<div class="container-fluid px-4">
<?php $flash = flash_get(); if ($flash): ?>
  <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= h($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
