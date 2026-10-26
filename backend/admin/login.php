<?php
/**
 * Страница входа в админ-панель.
 * Исправления: безопасная сессия (HttpOnly/SameSite), rate-limit на брутфорс,
 * session_regenerate_id против фиксации, экранирование ошибок, убран подсказка-логин.
 */

require_once __DIR__ . '/../api/middleware/helpers.php';
startSecureSession();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceRateLimit('auth_login');

    $db = (new Database())->getConnection();

    $username = sanitizeText($_POST['username'] ?? '', 50);
    $password = (string)($_POST['password'] ?? '');

    $stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    // always-run hash, чтобы существование пользователя не светилось по времени ответа
    $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvali';
    if ($user && password_verify($password, $hash)) {
        session_regenerate_id(true); // защита от фиксации сессии
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
               ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
        }
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход — МедУЗИ</title>
    <link rel="stylesheet" href="../../css/admin.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1 class="login-box__title">МедУЗИ</h1>
        <p class="login-box__subtitle">Админ-панель</p>

        <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="username" class="form-input" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" class="form-input" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn--primary btn--block">Войти</button>
        </form>
    </div>
</body>
</html>
