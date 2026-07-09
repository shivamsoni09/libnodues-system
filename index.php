<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
header('Location: ' . ($user ? dashboard_url_for_role($user['role_key']) : 'apply.php'));
exit;
