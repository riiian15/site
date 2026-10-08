<?php
$pageTitle = 'Заявки';
require_once '../config/database.php';
$db = (new Database())->getConnection();

// Изменение статуса
if (isset($_GET['status']) && isset($_GET['id'])) {
    $stmt = $db->prepare("UPDATE orders SET status = :s WHERE id = :id");
    $stmt->execute([':s' => $_GET['status'], ':id' => $_GET['id']]);
    header('Location: orders.php');
    exit;
}

$orders = $db->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();

foreach ($orders as &$order) {
    $stmt = $db->prepare("SELECT oi.*, p.name as product_name FROM order_items oi 
                          LEFT JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = :id");
    $stmt->execute([':id' => $order['id']]);
    $order['items'] = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<?php foreach ($orders as $order): ?>
<div class="panel order-panel">
    <div class="panel__header">
        <div>
            <h2>Заявка #<?= $order['id'] ?></h2>
            <p class="text-muted"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
        </div>
        <div class="order-status">
            <span class="badge badge--<?= $order['status'] ?>"><?= $order['status'] ?></span>
            <select onchange="location.href='?id=<?= $order['id'] ?>&status='+this.value" class="form-input">
                <option value="">Изменить статус</option>
                <option value="new">Новая</option>
                <option value="processing">В работе</option>
                <option value="completed">Завершена</option>
                <option value="cancelled">Отменена</option>
            </select>
        </div>
    </div>
    
    <div class="order-info">
        <div><strong>Клиент:</strong> <?= htmlspecialchars($order['name']) ?></div>
        <div><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></div>
        <?php if ($order['email']): ?>
            <div><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></div>
        <?php endif; ?>
        <div><strong>Тип:</strong> <?= $order['order_type'] ?></div>
        <?php if ($order['comment']): ?>
            <div><strong>Комментарий:</strong> <?= nl2br(htmlspecialchars($order['comment'])) ?></div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($order['items'])): ?>
        <h3>Позиции</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Тип</th>
                    <th>Кол-во</th>
                    <th>Цена</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name'] ?? 'Без товара') ?></td>
                        <td><?= $item['item_type'] ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= number_format($item['price'], 0, ',', ' ') ?> ₽</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="order-total">Итого: <strong><?= number_format($order['total_amount'], 0, ',', ' ') ?> ₽</strong></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($orders)): ?>
    <div class="panel"><p class="empty">Заявок пока нет</p></div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>