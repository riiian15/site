<?php
/**
 * Проверка авторизации в админ-панели. Сессию должен открыть session.php
 * (он подключается первым в каждой странице админки).
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>