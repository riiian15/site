<?php
/** Корректный выход: очистка сессии + удаление cookie (параметры задаёт startSecureSession). */
require_once __DIR__ . '/../api/middleware/helpers.php';
startSecureSession();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
