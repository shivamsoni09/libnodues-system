<?php
/**
 * OPTIONAL — creates test staff accounts (Front Desk, E-Resources, Librarian)
 * so you can try out the full workflow before handing the system to real staff.
 *
 * This script is NEVER run automatically by install.sh. Run it yourself,
 * only if you want it, from the app directory:
 *
 *     php seed.php
 *
 * All test accounts use the password: Test@1234
 * Change or delete them from Admin > Users before going live.
 */
require_once __DIR__ . '/includes/db.php';

echo "This creates TEST staff accounts with a shared known password (Test@1234).\n";
echo "Do not run this on a production system that real staff will use — \n";
echo "create real accounts from Admin > Users instead.\n";
echo "Continue? [y/N] ";
$answer = trim(fgets(STDIN));
if (strtolower($answer) !== 'y') {
    echo "Cancelled.\n";
    exit(0);
}

$pdo = db();

$accounts = [
    ['full_name' => 'Front Desk Staff',   'username' => 'frontdesk',  'email' => 'frontdesk@test.local',  'role_key' => 'frontdesk'],
    ['full_name' => 'E-Resources Staff',  'username' => 'eresources', 'email' => 'eresources@test.local', 'role_key' => 'eresources'],
    ['full_name' => 'Head Librarian',     'username' => 'librarian',  'email' => 'librarian@test.local',  'role_key' => 'librarian'],
];

$hash = password_hash('Test@1234', PASSWORD_DEFAULT);
$insert = $pdo->prepare(
    'INSERT IGNORE INTO users (full_name, username, email, password_hash, role_id, active)
     SELECT ?, ?, ?, ?, id, 1 FROM roles WHERE role_key = ?'
);

$created = 0;
foreach ($accounts as $acct) {
    $insert->execute([$acct['full_name'], $acct['username'], $acct['email'], $hash, $acct['role_key']]);
    if ($insert->rowCount() > 0) {
        $created++;
        echo "Created: {$acct['username']} / {$acct['email']} (role: {$acct['role_key']}, password: Test@1234)\n";
    } else {
        echo "Already exists: {$acct['username']} ({$acct['email']})\n";
    }
}

echo "Done. $created new test account(s) created.\n";
