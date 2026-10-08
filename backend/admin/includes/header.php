<?php require_once __DIR__ . '/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — МедУЗИ</title>
    <link rel="stylesheet" href="../../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="admin-content">
            <header class="admin-header">
                <h1 class="admin-header__title"><?= $pageTitle ?? 'Админ-панель' ?></h1>
                <div class="admin-header__user">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="logout.php" class="btn btn--sm btn--outline">Выйти</a>
                </div>
            </header>
            
            <main class="admin-main">