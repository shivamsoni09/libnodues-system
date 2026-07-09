<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role(['admin']);

$roles = db()->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$depts = db()->query('SELECT * FROM departments WHERE active=1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['full_name']);
        $email    = trim($_POST['email']);
        $username = trim($_POST['username']);
        $roleId   = (int) $_POST['role_id'];
        $temp     = bin2hex(random_bytes(4));

        // Auto-generate a username from the email if left blank
        if ($username === '') {
            $username = strtolower(preg_replace('/[^a-z0-9]/i', '', strstr($email, '@', true) ?: $email));
        }

        $exists = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $exists->execute([$email, $username]);
        if ($exists->fetch()) {
            flash_set('danger', 'A user with that email or username already exists.');
        } else {
            db()->prepare('INSERT INTO users (full_name, username, email, password_hash, role_id) VALUES (?,?,?,?,?)')
                ->execute([$name, $username, $email, password_hash($temp, PASSWORD_DEFAULT), $roleId]);
            log_audit($admin['id'], 'create_user', "Created $username ($email) with temp password");
            flash_set('success', "Staff account created. Username: $username · Temporary password: $temp (share securely and ask them to change it).");
        }
    } elseif ($action === 'toggle_active') {
        $id = (int) $_POST['id'];
        db()->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$id]);
        log_audit($admin['id'], 'toggle_user', "Toggled active for user #$id");
        flash_set('success', 'User status updated.');
    } elseif ($action === 'change_role') {
        $id = (int) $_POST['id'];
        $roleId = (int) $_POST['role_id'];
        db()->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roleId, $id]);
        log_audit($admin['id'], 'change_role', "User #$id role -> $roleId");
        flash_set('success', 'Role updated.');
    } elseif ($action === 'reset_password') {
        $id = (int) $_POST['id'];
        $temp = bin2hex(random_bytes(4));
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        log_audit($admin['id'], 'reset_password', "Reset password for user #$id");
        flash_set('success', "Password reset. Temporary password: $temp");
    }
    header('Location: users.php');
    exit;
}

$users = db()->query(
    'SELECT u.*, r.role_key, r.role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC'
)->fetchAll();

$page_title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-4">
  <div class="card-body">
    <h6>Create Staff Account</h6>
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="col-md-3"><input name="full_name" class="form-control" placeholder="Full name" required></div>
      <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
      <div class="col-md-2"><input name="username" class="form-control" placeholder="Username (auto if blank)"></div>
      <div class="col-md-3">
        <select name="role_id" class="form-select" required>
          <?php foreach ($roles as $r): if ($r['role_key']==='user') continue; ?>
            <option value="<?= $r['id'] ?>"><?= h($r['role_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['full_name']) ?></td>
          <td><?= h($u['username'] ?: '—') ?></td>
          <td><?= h($u['email']) ?></td>
          <td>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="change_role">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <select name="role_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= $r['id']==$u['role_id']?'selected':'' ?>><?= h($r['role_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm <?= $u['active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                <?= $u['active'] ? 'Active' : 'Disabled' ?>
              </button>
            </form>
          </td>
          <td><?= h(date('d M Y', strtotime($u['created_at']))) ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset password for this user?')">Reset Password</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
