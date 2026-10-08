<?php
$current = basename($_SERVER['PHP_SELF']);
$menu = [
    'index.php' => 'Дашборд',
    'products.php' => 'Товары',
    'product-edit.php' => 'Добавить товар',
    'orders.php' => 'Заявки',
];
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar__logo">
        <a href="index.php">МедУЗИ</a>
    </div>
    <nav class="admin-sidebar__nav">
        <?php foreach ($menu as $file => $title): ?>
            <a href="<?= $file ?>" class="admin-sidebar__link <?= $current === $file ? 'active' : '' ?>">
                <?= $title ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar__footer">
        <a href="../../index.html" target="_blank">Открыть сайт</a>
    </div>
</aside>