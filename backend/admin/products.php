<?php
$pageTitle = 'Товары';
require_once '../config/database.php';
$db = (new Database())->getConnection();

// Удаление
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $_GET['delete']]);
    header('Location: products.php');
    exit;
}

$products = $db->query("SELECT p.*, c.name as category_name FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        ORDER BY p.created_at DESC")->fetchAll();

include 'includes/header.php';
?>

<div class="panel">
    <div class="panel__header">
        <h2>Список товаров</h2>
        <a href="product-edit.php" class="btn btn--primary">Добавить товар</a>
    </div>
    
    <?php if (empty($products)): ?>
        <p class="empty">Товаров пока нет</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Бренд</th>
                    <th>Категория</th>
                    <th>Состояние</th>
                    <th>Цена</th>
                    <th>Год</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['brand']) ?></td>
                        <td><?= htmlspecialchars($p['category_name']) ?></td>
                        <td>
                            <span class="badge badge--<?= $p['product_condition'] ?>">
                                <?= $p['product_condition'] === 'new' ? 'Новый' : 'Б/У' ?>
                            </span>
                        </td>
                        <td><?= number_format($p['price'], 0, ',', ' ') ?> ₽</td>
                        <td><?= $p['year'] ?></td>
                        <td class="actions">
                            <a href="product-edit.php?id=<?= $p['id'] ?>" class="btn btn--sm btn--secondary">Изменить</a>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn--sm btn--danger" 
                               onclick="return confirm('Удалить товар?')">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>