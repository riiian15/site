<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();

$id = $_GET['id'] ?? null;
$product = null;
$specs = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT * FROM product_specs WHERE product_id = :id");
    $stmt->execute([':id' => $id]);
    $specs = $stmt->fetchAll();
}

$categories = $db->query("SELECT * FROM categories")->fetchAll();

// Сохранение
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'brand' => $_POST['brand'],
        'year' => $_POST['year'],
        'price' => $_POST['price'],
        'product_condition' => $_POST['condition'],
        'category_id' => $_POST['category_id'],
        'description' => $_POST['description']
    ];
    
    if ($id) {
        $stmt = $db->prepare("UPDATE products SET name=:name, brand=:brand, year=:year, 
                              price=:price, condition=:condition, category_id=:category_id, 
                              description=:description WHERE id=:id");
        $stmt->execute(array_merge($data, [':id' => $id]));
        
        $db->prepare("DELETE FROM product_specs WHERE product_id = :id")->execute([':id' => $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO products (name, brand, year, price, condition, category_id, description) 
                              VALUES (:name, :brand, :year, :price, :condition, :category_id, :description)");
        $stmt->execute($data);
        $id = $db->lastInsertId();
    }
    
    // Характеристики
    if (!empty($_POST['spec_name'])) {
        foreach ($_POST['spec_name'] as $i => $name) {
            if ($name && !empty($_POST['spec_value'][$i])) {
                $stmt = $db->prepare("INSERT INTO product_specs (product_id, spec_name, spec_value) VALUES (:pid, :n, :v)");
                $stmt->execute([':pid' => $id, ':n' => $name, ':v' => $_POST['spec_value'][$i]]);
            }
        }
    }
    
    header('Location: products.php');
    exit;
}

$pageTitle = $id ? 'Редактирование товара' : 'Добавление товара';
include 'includes/header.php';
?>

<div class="panel">
    <form method="POST" class="product-form">
        <div class="form-row">
            <div class="form-group">
                <label>Название *</label>
                <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Бренд *</label>
                <input type="text" name="brand" class="form-input" value="<?= htmlspecialchars($product['brand'] ?? '') ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Год выпуска *</label>
                <input type="number" name="year" class="form-input" value="<?= $product['year'] ?? '' ?>" required>
            </div>
            <div class="form-group">
                <label>Цена, ₽ *</label>
                <input type="number" name="price" class="form-input" value="<?= $product['price'] ?? '' ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Категория *</label>
                <select name="category_id" class="form-input" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($product['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Состояние *</label>
                <select name="condition" class="form-input" required>
                    <option value="new" <?= ($product['product_condition'] ?? '') === 'new' ? 'selected' : '' ?>>Новый</option>
                    <option value="used" <?= ($product['product_condition'] ?? '') === 'used' ? 'selected' : '' ?>>Б/У</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Описание</label>
            <textarea name="description" class="form-input" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Характеристики</label>
            <div id="specsList">
                <?php if (!empty($specs)): ?>
                    <?php foreach ($specs as $s): ?>
                        <div class="spec-row">
                            <input type="text" name="spec_name[]" class="form-input" placeholder="Название" value="<?= htmlspecialchars($s['spec_name']) ?>">
                            <input type="text" name="spec_value[]" class="form-input" placeholder="Значение" value="<?= htmlspecialchars($s['spec_value']) ?>">
                            <button type="button" class="btn btn--sm btn--danger" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn--sm btn--secondary" onclick="addSpec()" style="margin-top: 8px;">+ Добавить характеристику</button>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="products.php" class="btn btn--secondary">Отмена</a>
        </div>
    </form>
</div>

<script>
function addSpec() {
    const list = document.getElementById('specsList');
    const row = document.createElement('div');
    row.className = 'spec-row';
    row.innerHTML = `
        <input type="text" name="spec_name[]" class="form-input" placeholder="Название">
        <input type="text" name="spec_value[]" class="form-input" placeholder="Значение">
        <button type="button" class="btn btn--sm btn--danger" onclick="this.parentElement.remove()">×</button>
    `;
    list.appendChild(row);
}
</script>

<?php include 'includes/footer.php'; ?>