<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$staff = require_role(['frontdesk','admin']);

$search = trim($_GET['q'] ?? '');

$sql = "SELECT a.*,
               COALESCE(u.full_name, a.applicant_name)  AS display_name,
               COALESCE(u.library_card_no, a.applicant_library_card, '') AS library_card_no,
               dep.name AS dept_name,
               des.name AS desig_name
        FROM applications a
        LEFT JOIN users        u   ON u.id   = a.applicant_id
        LEFT JOIN departments  dep ON dep.id = a.department_id
        LEFT JOIN designations des ON des.id = a.designation_id
        WHERE a.current_stage = 'completed' AND a.status = 'approved'";

$params = [];
if ($search !== '') {
    $sql .= " AND (a.ticket_no LIKE ? OR a.applicant_name LIKE ? OR u.full_name LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY a.certificate_issued_at DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll();

$page_title = 'Print Certificates';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Approved Applications — Print Certificates <span class="badge bg-success"><?= count($apps) ?></span></h5>
  <form method="get" class="d-flex gap-2">
    <input name="q" class="form-control form-control-sm" value="<?= h($search) ?>"
           placeholder="Search by name or ticket…" style="width:240px">
    <button class="btn btn-outline-secondary btn-sm">Search</button>
    <?php if ($search): ?><a href="completed.php" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th>Ticket No.</th>
          <th>Applicant</th>
          <th>Designation / Dept</th>
          <th>Reason</th>
          <th>Approved On</th>
          <th>Koha</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$apps): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">
          <?= $search ? 'No results for "' . h($search) . '".' : 'No approved certificates yet.' ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($apps as $a): ?>
        <tr>
          <td class="fw-semibold"><?= h($a['ticket_no']) ?></td>
          <td>
            <?= h($a['display_name']) ?>
            <?php if (!$a['applicant_id']): ?><span class="badge bg-info text-dark ms-1">Walk-in</span><?php endif; ?>
            <?php if ($a['library_card_no']): ?><br><small class="text-muted">Card: <?= h($a['library_card_no']) ?></small><?php endif; ?>
          </td>
          <td class="small">
            <?= $a['desig_name'] ? h($a['desig_name']) : '—' ?>
            <?php if ($a['dept_name']): ?><br><span class="text-muted"><?= h($a['dept_name']) ?></span><?php endif; ?>
          </td>
          <td class="small text-muted"><?= h($a['reason'] ?: '—') ?></td>
          <td class="small"><?= h(date('d M Y', strtotime($a['certificate_issued_at']))) ?></td>
          <td>
            <?php if ($a['koha_clear']): ?>
              <span class="text-success fw-bold">✓ Clear</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a href="print_certificate.php?id=<?= $a['id'] ?>" target="_blank"
               class="btn btn-sm btn-outline-primary">📄 View Certificate</a>
            <a href="print_certificate.php?id=<?= $a['id'] ?>&print=1" target="_blank"
               class="btn btn-sm btn-primary">🖨️ Print</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
