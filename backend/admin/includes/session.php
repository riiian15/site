<?php
/**
 * Единая точка старта безопасной сессии для админ-панели.
 * Использует те же настройки cookie (HttpOnly, SameSite=Lax, Secure на HTTPS),
 * что и API, чтобы сессия была совместима между /admin и /api.
 */

require_once __DIR__ . '/../../api/middleware/helpers.php';

startSecureSession();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
