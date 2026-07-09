<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_dept' && trim($_POST['name'] ?? '') !== '') {
        db()->prepare('INSERT IGNORE INTO departments (name) VALUES (?)')->execute([trim($_POST['name'])]);
        flash_set('success', 'Department added.');
    } elseif ($action === 'add_desig' && trim($_POST['name'] ?? '') !== '') {
        db()->prepare('INSERT IGNORE INTO designations (name) VALUES (?)')->execute([trim($_POST['name'])]);
        flash_set('success', 'Designation added.');
    } elseif ($action === 'toggle_dept') {
        db()->prepare('UPDATE departments SET active = 1-active WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'toggle_desig') {
        db()->prepare('UPDATE designations SET active = 1-active WHERE id=?')->execute([(int)$_POST['id']]);
    }
    header('Location: departments.php');
    exit;
}

$depts = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$desigs = db()->query('SELECT * FROM designations ORDER BY name')->fetchAll();
$page_title = 'Departments & Designations';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h6>Departments</h6>
        <form method="post" class="d-flex gap-2 mb-3">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_dept">
          <input name="name" class="form-control" placeholder="New department name" required>
          <button class="btn btn-primary">Add</button>
        </form>
        <table class="table table-sm">
          <?php foreach ($depts as $d): ?>
            <tr>
              <td><?= h($d['name']) ?></td>
              <td class="text-end">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle_dept">
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm <?= $d['active'] ? 'btn-success' : 'btn-outline-secondary' ?>"><?= $d['active'] ? 'Active' : 'Inactive' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h6>Designations</h6>
        <form method="post" class="d-flex gap-2 mb-3">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_desig">
          <input name="name" class="form-control" placeholder="New designation name" required>
          <button class="btn btn-primary">Add</button>
        </form>
        <table class="table table-sm">
          <?php foreach ($desigs as $d): ?>
            <tr>
              <td><?= h($d['name']) ?></td>
              <td class="text-end">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle_desig">
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm <?= $d['active'] ? 'btn-success' : 'btn-outline-secondary' ?>"><?= $d['active'] ? 'Active' : 'Inactive' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
