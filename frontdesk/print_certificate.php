<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$staff = require_role(['frontdesk','admin','librarian']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    "SELECT a.*,
            COALESCE(u.full_name,       a.applicant_name)  AS display_name,
            COALESCE(u.library_card_no, a.applicant_library_card, '')      AS library_card_no,
            dep.name AS dept_name,
            des.name AS desig_name
     FROM applications a
     LEFT JOIN users        u   ON u.id   = a.applicant_id
     LEFT JOIN departments  dep ON dep.id = a.department_id
     LEFT JOIN designations des ON des.id = a.designation_id
     WHERE a.id = ? AND a.status = 'approved'"
);
$stmt->execute([$id]);
$app = $stmt->fetch();
if (!$app) {
    flash_set('danger', 'Certificate not found or application not yet approved.');
    header('Location: completed.php');
    exit;
}

// Fetch librarian who approved (last 'completed' workflow entry)
$approver = db()->prepare(
    "SELECT u.full_name FROM workflow w
     LEFT JOIN users u ON u.id = w.acted_by
     WHERE w.application_id = ? AND w.to_stage = 'completed'
     ORDER BY w.acted_at DESC LIMIT 1"
);
$approver->execute([$id]);
$librarianName = $approver->fetchColumn() ?: 'Head Librarian';

$certNo   = 'CERT/' . date('Y') . '/' . str_pad($app['id'], 5, '0', STR_PAD_LEFT);
$issuedOn = $app['certificate_issued_at']
    ? date('d F Y', strtotime($app['certificate_issued_at']))
    : date('d F Y');

$verifyUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'library') . '/track.php?ticket=' . urlencode($app['ticket_no']);
$qrUrl     = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>No Dues Certificate — <?= h($app['ticket_no']) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<style>
  /* ── Screen wrapper ───────────────────────────────────── */
  body { background: #e8eaf0; font-family: 'Times New Roman', Times, serif; }

  .screen-controls {
    max-width: 820px;
    margin: 1.5rem auto .75rem;
    display: flex; gap: .5rem; justify-content: flex-end;
  }

  /* ── A4 page shell ───────────────────────────────────── */
  .cert-page {
    max-width: 794px;          /* ~A4 width at 96 dpi */
    min-height: 1123px;        /* ~A4 height at 96 dpi */
    margin: 0 auto 3rem;
    background: #fff;
    padding: 0;
    box-shadow: 0 4px 24px rgba(0,0,0,.18);
    position: relative;
    page-break-after: always;
  }

  /* ── Outer border decoration ─────────────────────────── */
  .cert-outer-border {
    border: 10px solid #1a3a5c;
    margin: 18px;
    min-height: calc(1123px - 36px);
    position: relative;
    display: flex; flex-direction: column;
  }
  .cert-outer-border::before {
    content: '';
    position: absolute; inset: 6px;
    border: 2px solid #c9a84c;
    pointer-events: none;
  }

  /* ── Header band ─────────────────────────────────────── */
  .cert-header {
    background: #1a3a5c;
    color: #fff;
    text-align: center;
    padding: 1.5rem 2rem 1.25rem;
  }
  .cert-header .inst-name {
    font-size: 1.55rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: .15rem;
  }
  .cert-header .inst-sub {
    font-size: .9rem;
    opacity: .85;
    font-style: italic;
  }

  /* ── Gold divider ────────────────────────────────────── */
  .cert-gold-line {
    height: 4px;
    background: linear-gradient(90deg, #c9a84c 0%, #f5d98b 50%, #c9a84c 100%);
  }

  /* ── Certificate title ───────────────────────────────── */
  .cert-title-block {
    text-align: center;
    padding: 1.6rem 2rem .8rem;
  }
  .cert-title-block h1 {
    font-size: 2rem;
    letter-spacing: .14em;
    font-weight: 700;
    color: #1a3a5c;
    text-transform: uppercase;
    margin: 0;
  }
  .cert-title-block .cert-subtitle {
    font-size: .95rem;
    color: #555;
    margin-top: .3rem;
    font-style: italic;
  }
  .cert-no-badge {
    display: inline-block;
    background: #f4f6fa;
    border: 1px solid #c9a84c;
    border-radius: 4px;
    padding: .15rem .75rem;
    font-size: .78rem;
    color: #7a6220;
    margin-top: .5rem;
    letter-spacing: .06em;
  }

  /* ── Prose line ──────────────────────────────────────── */
  .cert-prose {
    text-align: center;
    font-size: 1.05rem;
    color: #333;
    padding: .5rem 3rem .25rem;
    line-height: 1.7;
  }

  /* ── Details table ───────────────────────────────────── */
  .cert-details {
    margin: 1rem 3rem;
    border-collapse: collapse;
    width: calc(100% - 6rem);
    font-size: .97rem;
  }
  .cert-details tr { border-bottom: 1px dashed #dde2e8; }
  .cert-details tr:last-child { border-bottom: none; }
  .cert-details th {
    width: 36%;
    padding: .55rem .75rem;
    color: #1a3a5c;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
  }
  .cert-details td {
    padding: .55rem .75rem;
    color: #222;
  }
  .cert-details .colon { padding: 0; color: #555; width: 1%; }

  /* ── Koha clearance box ──────────────────────────────── */
  .cert-clearance {
    margin: 1rem 3rem;
    background: #f0f7f0;
    border-left: 4px solid #2e7d32;
    padding: .6rem 1rem;
    font-size: .93rem;
    border-radius: 0 4px 4px 0;
    color: #1b5e20;
  }
  .cert-clearance strong { font-size: 1rem; }

  /* ── Signature section ───────────────────────────────── */
  .cert-signatures {
    margin-top: auto;
    padding: 2rem 3rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
  }
  .cert-sig-block { text-align: center; min-width: 170px; }
  .cert-sig-line {
    border-top: 1.5px solid #333;
    margin-bottom: .3rem;
    width: 180px;
  }
  .cert-sig-block .sig-name { font-weight: 700; font-size: .9rem; color: #1a3a5c; }
  .cert-sig-block .sig-title { font-size: .78rem; color: #666; }

  /* ── QR code (center of sigs) ────────────────────────── */
  .cert-qr { text-align: center; }
  .cert-qr img { display: block; margin: 0 auto .2rem; }
  .cert-qr small { font-size: .68rem; color: #888; }

  /* ── Footer strip ────────────────────────────────────── */
  .cert-footer-strip {
    background: #f4f6fa;
    border-top: 2px solid #1a3a5c;
    padding: .5rem 2rem;
    font-size: .75rem;
    color: #666;
    display: flex;
    justify-content: space-between;
  }

  /* ── Print styles ─────────────────────────────────────── */
  @media print {
    @page { size: A4 portrait; margin: 0; }
    body { background: #fff !important; }
    .screen-controls { display: none !important; }
    .cert-page {
      max-width: 100%;
      min-height: 100vh;
      box-shadow: none;
      margin: 0;
    }
    .cert-outer-border { min-height: calc(100vh - 36px); }
  }
</style>
</head>
<body>

<!-- Print / Back controls (screen only) -->
<div class="screen-controls d-print-none">
  <a href="completed.php" class="btn btn-outline-secondary btn-sm">← Back to List</a>
  <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Print / Save as PDF</button>
</div>

<!-- ───────── Certificate page ───────── -->
<div class="cert-page">
  <div class="cert-outer-border">

    <!-- Header -->
    <div class="cert-header">
      <div class="inst-name">📚 <?= h(APP_NAME) ?></div>
      <div class="inst-sub">Library Clearance Division &middot; Circulation Department</div>
    </div>
    <div class="cert-gold-line"></div>

    <!-- Title -->
    <div class="cert-title-block">
      <h1>No Dues Certificate</h1>
      <div class="cert-subtitle">This certificate is issued as proof of library clearance</div>
      <div class="cert-no-badge">Certificate No: <?= h($certNo) ?> &nbsp;&bull;&nbsp; Ticket: <?= h($app['ticket_no']) ?></div>
    </div>

    <!-- Prose -->
    <div class="cert-prose">
      This is to certify that the following individual has been duly verified and
      <strong>cleared of all library dues, obligations, and obligations</strong>
      maintained in the circulation system of this institution.
    </div>

    <!-- Details table -->
    <table class="cert-details">
      <tr>
        <th>Full Name</th>
        <td class="colon">:</td>
        <td><strong><?= h($app['display_name']) ?></strong></td>
      </tr>
      <?php if ($app['desig_name']): ?>
      <tr>
        <th>Designation</th>
        <td class="colon">:</td>
        <td><?= h($app['desig_name']) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($app['dept_name']): ?>
      <tr>
        <th>Department</th>
        <td class="colon">:</td>
        <td><?= h($app['dept_name']) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($app['library_card_no']): ?>
      <tr>
        <th>Library Card No.</th>
        <td class="colon">:</td>
        <td><?= h($app['library_card_no']) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($app['joining_date']): ?>
      <tr>
        <th>Date of Joining</th>
        <td class="colon">:</td>
        <td><?= h(date('d F Y', strtotime($app['joining_date']))) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($app['relieving_date']): ?>
      <tr>
        <th>Date of Relieving</th>
        <td class="colon">:</td>
        <td><?= h(date('d F Y', strtotime($app['relieving_date']))) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($app['reason']): ?>
      <tr>
        <th>Reason for Clearance</th>
        <td class="colon">:</td>
        <td><?= h($app['reason']) ?></td>
      </tr>
      <?php endif; ?>
      <tr>
        <th>Date of Issue</th>
        <td class="colon">:</td>
        <td><?= h($issuedOn) ?></td>
      </tr>
    </table>

    <!-- Koha clearance confirmation -->
    <div class="cert-clearance">
      ✔ <strong>Circulation Cleared</strong> — Koha ILS records confirm: no outstanding books,
      <?php if ($app['koha_fine_amount'] !== null && $app['koha_fine_amount'] == 0): ?>
      no pending fines, and no lost items on record.
      <?php else: ?>
      all outstanding matters resolved at the time of clearance.
      <?php endif; ?>
      <?php if ($app['koha_checked_at']): ?>
      <span style="float:right;font-size:.82rem">Verified: <?= h(date('d M Y', strtotime($app['koha_checked_at']))) ?></span>
      <?php endif; ?>
    </div>

    <!-- Signatures -->
    <div class="cert-signatures">
      <!-- Front Desk sig -->
      <div class="cert-sig-block">
        <div class="cert-sig-line"></div>
        <div class="sig-name">Front Desk / Circulation</div>
        <div class="sig-title">Verified &amp; Processed</div>
        <div class="sig-title" style="margin-top:.2rem"><?= h(date('d M Y')) ?></div>
      </div>

      <!-- QR code -->
      <div class="cert-qr">
        <img src="<?= h($qrUrl) ?>" width="100" height="100" alt="QR" onerror="this.style.display='none'">
        <small>Scan to verify online</small>
      </div>

      <!-- Librarian sig -->
      <div class="cert-sig-block">
        <div class="cert-sig-line"></div>
        <div class="sig-name"><?= h($librarianName) ?></div>
        <div class="sig-title">Head Librarian</div>
        <div class="sig-title" style="margin-top:.2rem"><?= h($issuedOn) ?></div>
      </div>
    </div>

    <!-- Footer strip -->
    <div class="cert-gold-line"></div>
    <div class="cert-footer-strip">
      <span>This certificate is system-generated and valid without a physical stamp.</span>
      <span>Verify at: <?= h(parse_url($verifyUrl, PHP_URL_HOST)) ?>/track.php</span>
    </div>

  </div><!-- /.cert-outer-border -->
</div><!-- /.cert-page -->

<script>
  // Auto-trigger print dialog if ?print=1 in URL
  if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
  }
</script>
</body>
</html>
