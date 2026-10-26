<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    $plain_password = 'admin123';
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE username = 'admin'");
    $stmt->execute([':hash' => $hashed_password]);
    
    echo "<h2 style='color: green;'>⚠️ Пароль сброшен! Смените его и удалите этот файл.</h2>";
    echo "<p>Логин: <b>admin</b></p>";
    echo "<p>Пароль: <b>admin123</b></p>";
    echo "<br><a href='admin/login.php'>Перейти к входу</a>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Ошибка:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>