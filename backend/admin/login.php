<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    $db = (new Database())->getConnection();
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
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
            <div class="alert alert--error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="username" class="form-input" required autofocus>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Войти</button>
        </form>
        
        <p class="login-box__hint">Логин: admin | Пароль: admin123</p>
    </div>
</body>
</html>