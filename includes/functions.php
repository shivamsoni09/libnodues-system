<?php
require_once __DIR__ . '/db.php';

/** Generates the next sequential ticket number for the current year, e.g. ND-2026-000001 */
function next_ticket_no(): string
{
    $pdo  = db();
    $year = (int) date('Y');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM ticket_sequence WHERE year = ? FOR UPDATE');
        $stmt->execute([$year]);
        $row = $stmt->fetch();

        if ($row === false) {
            $pdo->prepare('INSERT INTO ticket_sequence (year, last_number) VALUES (?, 1)')->execute([$year]);
            $next = 1;
        } else {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE ticket_sequence SET last_number = ? WHERE year = ?')->execute([$next, $year]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return sprintf('%s-%d-%06d', TICKET_PREFIX, $year, $next);
}

/** Records a stage transition in the workflow table. */
function log_workflow(int $application_id, ?string $from_stage, string $to_stage, string $action, ?int $acted_by): void
{
    $stmt = db()->prepare(
        'INSERT INTO workflow (application_id, from_stage, to_stage, action, acted_by)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$application_id, $from_stage, $to_stage, $action, $acted_by]);
}

/** Adds a remark tied to an application + stage. */
function add_remark(int $application_id, string $stage, string $remark, ?int $created_by): void
{
    if (trim($remark) === '') return;
    $stmt = db()->prepare(
        'INSERT INTO remarks (application_id, stage, remark, created_by) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$application_id, $stage, $remark, $created_by]);
}

/**
 * Matches a free-typed value against an existing row in departments/designations
 * (case-insensitive), or creates a new active row if nothing matches. Used by
 * the public apply form so department/designation stay plain text boxes while
 * admin reports and filters keep working against normalized rows.
 */
function find_or_create_lookup(string $table, string $value): int
{
    $value = trim($value);
    $find = db()->prepare("SELECT id FROM {$table} WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $find->execute([$value]);
    $id = $find->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $insert = db()->prepare("INSERT INTO {$table} (name, active) VALUES (?, 1)");
    $insert->execute([$value]);
    return (int) db()->lastInsertId();
}

function status_badge(string $status): string
{
    $map = [
        'pending'     => 'secondary',
        'in_progress' => 'warning text-dark',
        'approved'    => 'success',
        'rejected'    => 'danger',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

function stage_label(string $stage): string
{
    $map = [
        'submitted'  => 'Submitted',
        'frontdesk'  => 'Front Desk',
        'eresources' => 'E-Resources',
        'librarian'  => 'Librarian',
        'completed'  => 'Completed',
        'rejected'   => 'Rejected',
    ];
    return $map[$stage] ?? $stage;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
