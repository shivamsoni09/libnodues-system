<?php
/**
 * OPTIONAL — inserts 4 sample applications (one at each workflow stage)
 * so you have something to click through while testing.
 *
 * This script is NEVER run automatically by install.sh. Run it yourself,
 * only if you want it, from the app directory:
 *
 *     php demo.php
 *
 * Safe to run multiple times (uses INSERT IGNORE / unique ticket numbers).
 * Delete the demo applications from Admin > Reports before going live,
 * or simply leave a fresh, empty system for real applications.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "This creates 4 SAMPLE applications (one at each workflow stage) for testing.\n";
echo "Continue? [y/N] ";
$answer = trim(fgets(STDIN));
if (strtolower($answer) !== 'y') {
    echo "Cancelled.\n";
    exit(0);
}

$pdo = db();

// Make sure at least one department/designation exists for the demo rows
$deptId  = find_or_create_lookup('departments', 'Computer Science');
$desigId = find_or_create_lookup('designations', 'Student');

$rows = [
    ['stage' => 'frontdesk',  'status' => 'pending',     'name' => 'Demo Applicant A'],
    ['stage' => 'eresources', 'status' => 'in_progress', 'name' => 'Demo Applicant B'],
    ['stage' => 'librarian',  'status' => 'in_progress', 'name' => 'Demo Applicant C'],
    ['stage' => 'completed',  'status' => 'approved',    'name' => 'Demo Applicant D'],
];

foreach ($rows as $r) {
    $ticket = next_ticket_no();
    $stmt = $pdo->prepare(
        'INSERT INTO applications
           (ticket_no, applicant_name, applicant_email, applicant_phone,
            joining_date, relieving_date, department_id, designation_id,
            reason, current_stage, status, koha_checked, koha_clear,
            certificate_issued_at)
         VALUES (?, ?, ?, ?, CURDATE(), CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ticket, $r['name'], strtolower(str_replace(' ', '.', $r['name'])) . '@example.com', '9999999999',
        $deptId, $desigId, 'Course completion / graduation', $r['stage'], $r['status'],
        $r['stage'] !== 'frontdesk' ? 1 : 0,
        $r['stage'] !== 'frontdesk' ? 1 : 0,
        $r['stage'] === 'completed' ? date('Y-m-d H:i:s') : null,
    ]);
    $appId = (int) $pdo->lastInsertId();
    log_workflow($appId, null, $r['stage'], 'submitted', null);
    echo "Created {$ticket} — {$r['name']} at stage '{$r['stage']}'\n";
}

echo "Done.\n";
