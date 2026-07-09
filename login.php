<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    header('Location: ' . dashboard_url_for_role(current_user()['role_key']));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $user     = attempt_login($username, $pass);
    if ($user) {
        header('Location: ' . dashboard_url_for_role($user['role_key']));
        exit;
    }
    $error = 'Incorrect username or password.';
}
$page_title = 'Staff Login';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">
    <div class="card mt-5">
      <div class="card-body p-4">
        <h4 class="mb-1 text-center">Staff Login</h4>
        <p class="text-center text-muted small mb-3">Library staff only. <a href="apply.php">Apply for No Dues?</a></p>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required autofocus
                   value="<?= h($_POST['username'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Log in</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
