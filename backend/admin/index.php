<?php
$pageTitle = 'Дашборд';
require_once '../config/database.php';
$db = (new Database())->getConnection();

// Статистика
$stats = [
    'products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'new_orders' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn(),
    'revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn(),
];

$recentOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

include 'includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__label">Всего товаров</div>
        <div class="stat-card__value"><?= $stats['products'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Всего заявок</div>
        <div class="stat-card__value"><?= $stats['orders'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Новых заявок</div>
        <div class="stat-card__value stat-card__value--accent"><?= $stats['new_orders'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Выручка</div>
        <div class="stat-card__value"><?= number_format($stats['revenue'], 0, ',', ' ') ?> ₽</div>
    </div>
</div>

<div class="panel">
    <div class="panel__header">
        <h2>Последние заявки</h2>
        <a href="orders.php" class="btn btn--sm btn--outline">Все заявки</a>
    </div>
    
    <?php if (empty($recentOrders)): ?>
        <p class="empty">Заявок пока нет</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Клиент</th>
                    <th>Телефон</th>
                    <th>Тип</th>
                    <th>Сумма</th>
                    <th>Дата</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?= $order['id'] ?></td>
                        <td><?= htmlspecialchars($order['name']) ?></td>
                        <td><?= htmlspecialchars($order['phone']) ?></td>
                        <td><?= $order['order_type'] ?></td>
                        <td><?= number_format($order['total_amount'], 0, ',', ' ') ?> ₽</td>
                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                        <td><span class="badge badge--<?= $order['status'] ?>"><?= $order['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>