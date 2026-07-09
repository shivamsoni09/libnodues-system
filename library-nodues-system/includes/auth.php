<?php
require_once __DIR__ . '/db.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . rel_path('login.php'));
        exit;
    }
    return $user;
}

function require_role(array $allowed_roles): array
{
    $user = require_login();
    if (!in_array($user['role_key'], $allowed_roles, true)) {
        http_response_code(403);
        die('Access denied: your role (' . htmlspecialchars($user['role_key']) . ') cannot view this page.');
    }
    return $user;
}

/** Computes a relative path back to the app root from any sub-folder page. */
function rel_path(string $target): string
{
    $depth = substr_count(trim(dirname($_SERVER['PHP_SELF']), '/'), '/');
    // pages living directly in role sub-folders are 1 level deep
    $inRoot = !preg_match('#/(user|frontdesk|eresources|librarian|admin)(/|$)#', $_SERVER['PHP_SELF']);
    return $inRoot ? $target : '../' . $target;
}

function attempt_login(string $username, string $password): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, r.role_key, r.role_name FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.username = ? AND u.active = 1 LIMIT 1'
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        unset($row['password_hash']);
        $_SESSION['user'] = $row;
        log_audit($row['id'], 'login', 'User logged in');
        return $row;
    }
    return null;
}

function logout(): void
{
    $user = current_user();
    if ($user) {
        log_audit($user['id'], 'logout', 'User logged out');
    }
    $_SESSION = [];
    session_destroy();
}

function log_audit(?int $user_id, string $action, string $details = ''): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function dashboard_url_for_role(string $role_key): string
{
    return match ($role_key) {
        'user'       => 'user/dashboard.php',
        'frontdesk'  => 'frontdesk/dashboard.php',
        'eresources' => 'eresources/dashboard.php',
        'librarian'  => 'librarian/dashboard.php',
        'admin'      => 'admin/dashboard.php',
        default      => 'login.php',
    };
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}
