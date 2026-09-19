<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('mwh_local_admin');
    session_start();
}

function requireAdmin(): void
{
    if (empty($_SESSION['local_admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function currentAdmin(): array
{
    return [
        'id' => (int)($_SESSION['local_admin_id'] ?? 0),
        'name' => (string)($_SESSION['local_admin_name'] ?? ''),
        'email' => (string)($_SESSION['local_admin_email'] ?? ''),
        'role' => (string)($_SESSION['local_admin_role'] ?? ''),
    ];
}

function adminLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
