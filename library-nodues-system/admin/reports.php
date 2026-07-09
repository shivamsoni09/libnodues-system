<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role(['admin']);

$deptId = $_GET['department_id'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];
$params = [];
if ($deptId !== '') { $where[] = 'a.department_id = ?'; $params[] = $deptId; }
if ($from !== '')   { $where[] = 'a.created_at >= ?';   $params[] = $from . ' 00:00:00'; }
if ($to !== '')     { $where[] = 'a.created_at <= ?';   $params[] = $to . ' 23:59:59'; }
if ($status !== '') { $where[] = 'a.status = ?';        $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT a.ticket_no,
               COALESCE(u.full_name, a.applicant_name) AS full_name,
               COALESCE(u.library_card_no, a.applicant_library_card) AS library_card_no,
               d.name AS department, a.reason,
               a.status, a.current_stage, a.created_at, a.certificate_issued_at
        FROM applications a
        LEFT JOIN users u ON u.id = a.applicant_id
        LEFT JOIN departments d ON d.id = a.department_id
        $whereSql
        ORDER BY a.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="nodues_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Ticket No', 'Applicant', 'Card No', 'Department', 'Reason', 'Status', 'Stage', 'Submitted', 'Certificate Issued']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['ticket_no'], $r['full_name'], $r['library_card_no'], $r['department'], $r['reason'], $r['status'], $r['current_stage'], $r['created_at'], $r['certificate_issued_at']]);
    }
    fclose($out);
    exit;
}

$depts = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">Department</label>
        <select name="department_id" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptId==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" value="<?= h($from) ?>" class="form-control form-control-sm"></div>
      <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" value="<?= h($to) ?>" class="form-control form-control-sm"></div>
      <div class="col-md-2">
        <label class="form-label small">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['pending','in_progress','approved','rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= h(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-sm btn-primary">Filter</button>
        <a class="btn btn-sm btn-outline-success" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">Export CSV</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Ticket</th><th>Applicant</th><th>Dept</th><th>Status</th><th>Stage</th><th>Submitted</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['ticket_no']) ?></td><td><?= h($r['full_name']) ?></td><td><?= h($r['department']) ?></td>
          <td><?= status_badge($r['status']) ?></td><td><?= h(stage_label($r['current_stage'])) ?></td>
          <td><?= h(date('d M Y', strtotime($r['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No matching records.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
