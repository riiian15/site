<?php
/**
 * Добавление/редактирование товара в админ-панели.
 * Исправления: корректное имя колонки product_condition, обязательные поля
 * email/available, CSRF-защита, валидация, транзакция для specs.
 */

require_once __DIR__ . '/../api/middleware/helpers.php';
startSecureSession();
require_once __DIR__ . '/includes/auth_check.php'; // редирект неавторизованных + проверка роли

$db = (new Database())->getConnection();

$id      = (int)($_GET['id'] ?? 0);
$product = null;
$specs   = [];
$errors  = [];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: products.php');
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM product_specs WHERE product_id = :id ORDER BY id");
    $stmt->execute([':id' => $id]);
    $specs = $stmt->fetchAll();
}

// ---------- Сохранение ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name        = sanitizeText($_POST['name'] ?? '', 255);
    $brand       = sanitizeText($_POST['brand'] ?? '', 100);
    $year        = (int)($_POST['year'] ?? 0);
    $price       = (float)($_POST['price'] ?? -1);
    $condition   = $_POST['condition'] ?? '';
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $description = sanitizeText($_POST['description'] ?? '', 5000);
    $available   = isset($_POST['available']) ? 1 : 0;

    if (!textInRange($name, 2, 255))                 $errors[] = 'Название: от 2 до 255 символов';
    if (!textInRange($brand, 1, 100))                $errors[] = 'Укажите бренд';
    if ($year < 1900 || $year > 2100)                $errors[] = 'Год выпуска: 1900–2100';
    if ($price < 0)                                  $errors[] = 'Цена не может быть отрицательной';
    if (!in_array($condition, ['new', 'used'], true)) $errors[] = 'Некорректное состояние товара';
    $catOk = $db->prepare("SELECT COUNT(*) FROM categories WHERE id = :id");
    $catOk->execute([':id' => $categoryId]);
    if ((int)$catOk->fetchColumn() === 0)            $errors[] = 'Выберите существующую категорию';

    if (!$errors) {
        try {
            $db->beginTransaction();

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE products SET name = :name, brand = :brand, year = :year,
                                      price = :price, product_condition = :cond, category_id = :cat,
                                      description = :descr, available = :avail
                                      WHERE id = :id");
                $stmt->execute([
                    ':id' => $id, ':name' => $name, ':brand' => $brand, ':year' => $year,
                    ':price' => $price, ':cond' => $condition, ':cat' => $categoryId,
                    ':descr' => $description, ':avail' => $available,
                ]);
                $db->prepare("DELETE FROM product_specs WHERE product_id = :id")->execute([':id' => $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO products
                    (name, brand, year, price, product_condition, category_id, description, available)
                    VALUES (:name, :brand, :year, :price, :cond, :cat, :descr, :avail)");
                $stmt->execute([
                    ':name' => $name, ':brand' => $brand, ':year' => $year,
                    ':price' => $price, ':cond' => $condition, ':cat' => $categoryId,
                    ':descr' => $description, ':avail' => $available,
                ]);
                $id = (int)$db->lastInsertId();
            }

            if (!empty($_POST['spec_name']) && is_array($_POST['spec_name'])) {
                $ins = $db->prepare("INSERT INTO product_specs (product_id, spec_name, spec_value)
                                     VALUES (:pid, :n, :v)");
                foreach ($_POST['spec_name'] as $i => $sName) {
                    $sValue = $_POST['spec_value'][$i] ?? '';
                    $sName  = sanitizeText((string)$sName, 100);
                    $sValue = sanitizeText((string)$sValue, 255);
                    if ($sName !== '' && $sValue !== '') {
                        $ins->execute([':pid' => $id, ':n' => $sName, ':v' => $sValue]);
                    }
                }
            }

            $db->commit();
            header('Location: products.php');
            exit;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('admin product-edit: ' . $e->getMessage());
            $errors[] = 'Не удалось сохранить товар. Попробуйте ещё раз.';
        }
    }

    // Возвращаем введённые значения при ошибке валидации
    $product = array_merge($product ?? [], [
        'name' => $name, 'brand' => $brand, 'year' => $year, 'price' => $price,
        'product_condition' => $condition, 'category_id' => $categoryId,
        'description' => $description, 'available' => $available,
    ]);
    if (!empty($_POST['spec_name'])) {
        $specs = [];
        foreach ($_POST['spec_name'] as $i => $n) {
            if ($n !== '' && ($_POST['spec_value'][$i] ?? '') !== '') {
                $specs[] = ['spec_name' => $n, 'spec_value' => $_POST['spec_value'][$i]];
            }
        }
    }
}

$categories = $db->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
$pageTitle  = $id > 0 ? 'Редактирование товара' : 'Добавление товара';
include __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <?php if ($errors): ?>
        <div class="alert alert--error">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="product-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Название *</label>
                <input type="text" name="name" class="form-input" required maxlength="255"
                       value="<?= htmlspecialchars($product['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Бренд *</label>
                <input type="text" name="brand" class="form-input" required maxlength="100"
                       value="<?= htmlspecialchars($product['brand'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Год выпуска *</label>
                <input type="number" name="year" class="form-input" required min="1900" max="2100"
                       value="<?= htmlspecialchars((string)($product['year'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Цена, ₽ *</label>
                <input type="number" name="price" class="form-input" required min="0" step="0.01"
                       value="<?= htmlspecialchars((string)($product['price'] ?? '')) ?>">
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
            <label class="checkbox-label">
                <input type="checkbox" name="available" value="1" <?= !isset($product['available']) || $product['available'] ? 'checked' : '' ?>>
                Товар доступен для заказа
            </label>
        </div>

        <div class="form-group">
            <label>Характеристики</label>
            <div id="specsList">
                <?php foreach ($specs as $s): ?>
                    <div class="spec-row">
                        <input type="text" name="spec_name[]" class="form-input" placeholder="Название"
                               value="<?= htmlspecialchars($s['spec_name']) ?>">
                        <input type="text" name="spec_value[]" class="form-input" placeholder="Значение"
                               value="<?= htmlspecialchars($s['spec_value']) ?>">
                        <button type="button" class="btn btn--sm btn--danger" onclick="this.parentElement.remove()">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn--sm btn--secondary" onclick="addSpec()" style="margin-top: 8px;">
                + Добавить характеристику
            </button>
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

<?php include __DIR__ . '/includes/footer.php'; ?>
