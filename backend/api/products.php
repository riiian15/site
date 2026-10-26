<?php
/**
 * GET /api/products.php[?action=categories|product&fields=...]
 * POST/PUT/DELETE — управление товарами (требует авторизации администратора).
 *
 * Улучшения: пагинация, сортировка по белому списку, агрегирующий запрос
 * для спецификаций/изображений (без N+1), валидация, CSRF, rate-limit.
 */

require_once __DIR__ . '/middleware/helpers.php';

handleCors();
startSecureSession();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

enforceRateLimit('api_read');

switch ($method) {
    case 'GET':
        if ($action === 'categories') {
            getCategories($db);
        } elseif ($action === 'product') {
            getProduct($db, (int)($_GET['id'] ?? 0));
        } else {
            getProducts($db);
        }
        break;

    case 'POST':
        if ($action === 'create') { requireAdmin(); verifyCsrf(); createProduct($db); }
        jsonError('Неизвестное действие', 400);
        break;

    case 'PUT':
        if ($action === 'update') { requireAdmin(); verifyCsrf(); updateProduct($db, (int)($_GET['id'] ?? 0)); }
        jsonError('Неизвестное действие', 400);
        break;

    case 'DELETE':
        if ($action === 'delete') { requireAdmin(); verifyCsrf(); deleteProduct($db, (int)($_GET['id'] ?? 0)); }
        jsonError('Неизвестное действие', 400);
        break;

    default:
        jsonError('Метод не поддерживается', 405);
}

/* ---------- helpers ---------- */

function requireAdmin(): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        jsonError('Требуется авторизация администратора', 401);
    }
}

const SORTABLE = [
    'created_at' => 'p.created_at', 'price' => 'p.price',
    'name' => 'p.name', 'year' => 'p.year',
];

function getCategories(PDO $db): void
{
    $stmt = $db->query("SELECT id, name, description, has_subcategories FROM categories ORDER BY id");
    jsonOut($stmt->fetchAll());
}

function getProducts(PDO $db): void
{
    [$page, $perPage, $offset] = pagination(24);

    $where  = [];
    $params = [];

    if (!empty($_GET['category'])) {
        $where[] = "p.category_id = :category";
        $params[':category'] = (int)$_GET['category'];
    }
    if (!empty($_GET['condition'])) {
        $where[] = "p.product_condition = :cond";
        $params[':cond'] = oneOf($_GET['condition'], ['new', 'used'], 'condition');
    }
    if (!empty($_GET['brand'])) {
        $where[] = "p.brand LIKE :brand";
        $params[':brand'] = sanitizeText($_GET['brand'], 100) . '%';
    }
    if (!empty($_GET['yearFrom'])) {
        $where[] = "p.year >= :yearFrom";
        $params[':yearFrom'] = max(1900, min(2100, (int)$_GET['yearFrom']));
    }
    if (!empty($_GET['yearTo'])) {
        $where[] = "p.year <= :yearTo";
        $params[':yearTo'] = max(1900, min(2100, (int)$_GET['yearTo']));
    }
    if (isset($_GET['priceFrom']) && $_GET['priceFrom'] !== '') {
        $where[] = "p.price >= :priceFrom";
        $params[':priceFrom'] = max(0, (float)$_GET['priceFrom']);
    }
    if (isset($_GET['priceTo']) && $_GET['priceTo'] !== '') {
        $where[] = "p.price <= :priceTo";
        $params[':priceTo'] = max(0, (float)$_GET['priceTo']);
    }
    if (!empty($_GET['search'])) {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], sanitizeText($_GET['search'], 100)) . '%';
        $where[] = "(p.name LIKE :s1 ESCAPE '\\\\' OR p.description LIKE :s2 ESCAPE '\\\\' OR p.brand LIKE :s3 ESCAPE '\\\\')";
        $params[':s1'] = $q; $params[':s2'] = $q; $params[':s3'] = $q;
    }
    // Только доступные товары (для публичного каталога)
    if (!empty($_GET['onlyAvailable'])) {
        $where[] = "p.available = 1";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Сортировка: только из белого списка
    $sortBy   = SORTABLE[$_GET['sort'] ?? ''] ?? 'p.created_at';
    $sortDir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    // Общее количество для пагинации
    $stmt = $db->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $whereSql
            ORDER BY $sortBy $sortDir, p.id DESC
            LIMIT :lim OFFSET :off";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Агрегируем specs/images одним запросом (устранение N+1)
    attachRelations($db, $products);

    jsonOut([
        'data'     => $products,
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'pages'    => (int)ceil($total / $perPage),
    ]);
}

function attachRelations(PDO $db, array &$products): void
{
    if (!$products) return;
    $ids = array_column($products, 'id');
    $in  = implode(',', array_map('intval', $ids));

    $specs = $db->query(
        "SELECT product_id, spec_name, spec_value FROM product_specs WHERE product_id IN ($in)"
    )->fetchAll();
    $images = $db->query(
        "SELECT product_id, image_path FROM product_images WHERE product_id IN ($in)
         ORDER BY is_primary DESC, sort_order ASC"
    )->fetchAll();

    $specMap = $imgMap = [];
    foreach ($specs as $s)  $specMap[$s['product_id']][$s['spec_name']] = $s['spec_value'];
    foreach ($images as $i) $imgMap[$i['product_id']][] = $i['image_path'];

    foreach ($products as &$p) {
        $p['specs']   = $specMap[$p['id']] ?? new stdClass(); // {} в JSON
        $p['images']  = $imgMap[$p['id']]  ?? [];
    }
}

function getProduct(PDO $db, int $id): void
{
    if ($id <= 0) jsonError('Некорректный идентификатор товара', 422);

    $stmt = $db->prepare("SELECT p.*, c.name AS category_name
                          FROM products p
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();

    if (!$product) jsonError('Товар не найден', 404);

    $arr = [$product];
    attachRelations($db, $arr);
    jsonOut($arr[0]);
}

/** Валидация полей товара; возвращает готовый для SQL массив. */
function validateProductPayload(array $data): array
{
    need($data, 'name', 'brand', 'year', 'price', 'condition', 'category_id');

    $out = [
        'name'        => sanitizeText($data['name'], 255),
        'brand'       => sanitizeText($data['brand'], 100),
        'year'        => (int)$data['year'],
        'price'       => (float)$data['price'],
        'condition'   => oneOf($data['condition'], ['new', 'used'], 'condition'),
        'category_id' => (int)$data['category_id'],
        'description' => sanitizeText($data['description'] ?? '', 5000),
    ];

    if (!textInRange($out['name'], 2, 255))       jsonError('Название: 2–255 символов', 422);
    if ($out['year'] < 1900 || $out['year'] > 2100) jsonError('Некорректный год', 422);
    if ($out['price'] < 0)                  jsonError('Цена не может быть отрицательной', 422);

    if (isset($data['available'])) {
        $out['available'] = !empty($data['available']) ? 1 : 0;
    }
    return $out;
}

function saveSpecs(PDO $db, int $productId, array $specs): void
{
    $stmt = $db->prepare("INSERT INTO product_specs (product_id, spec_name, spec_value)
                          VALUES (:pid, :name, :value)");
    foreach ($specs as $name => $value) {
        $stmt->execute([
            ':pid'   => $productId,
            ':name'  => sanitizeText((string)$name, 100),
            ':value' => sanitizeText((string)$value, 255),
        ]);
    }
}

function createProduct(PDO $db): void
{
    $data = validateProductPayload(readJsonBody());

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO products
            (name, brand, year, price, product_condition, category_id, description, available)
            VALUES (:name, :brand, :year, :price, :product_condition, :category_id, :description, :available)");
        $stmt->execute([
            ':name' => $data['name'], ':brand' => $data['brand'], ':year' => $data['year'],
            ':price' => $data['price'], ':product_condition' => $data['condition'],
            ':category_id' => $data['category_id'], ':description' => $data['description'],
            ':available' => $data['available'] ?? 1,
        ]);
        $productId = (int)$db->lastInsertId();

        $body = readJsonBodyFallback();
        if (!empty($body['specs']) && is_array($body['specs'])) {
            saveSpecs($db, $productId, $body['specs']);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('createProduct: ' . $e->getMessage());
        jsonError('Не удалось создать товар', 500);
    }

    jsonOut(['success' => true, 'id' => $productId], 201);
}

// php://input можно прочитать один раз — кэшируем тело запроса.
$GLOBALS['__body_cache'] = null;
function readJsonBodyCached(): array
{
    if ($GLOBALS['__body_cache'] === null) {
        $GLOBALS['__body_cache'] = readJsonBody();
    }
    return $GLOBALS['__body_cache'];
}
function readJsonBodyFallback(): array
{
    return readJsonBodyCached();
}

function updateProduct(PDO $db, int $id): void
{
    if ($id <= 0) jsonError('Некорректный идентификатор товара', 422);
    $data = validateProductPayload(readJsonBodyCached());

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE products SET
            name = :name, brand = :brand, year = :year, price = :price,
            product_condition = :product_condition, category_id = :category_id,
            description = :description, available = :available
            WHERE id = :id");
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'], ':brand' => $data['brand'], ':year' => $data['year'],
            ':price' => $data['price'], ':product_condition' => $data['condition'],
            ':category_id' => $data['category_id'], ':description' => $data['description'],
            ':available' => $data['available'] ?? 1,
        ]);

        $body = readJsonBodyCached();
        if (array_key_exists('specs', $body) && is_array($body['specs'])) {
            $db->prepare("DELETE FROM product_specs WHERE product_id = :pid")->execute([':pid' => $id]);
            saveSpecs($db, $id, $body['specs']);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('updateProduct: ' . $e->getMessage());
        jsonError('Не удалось обновить товар', 500);
    }

    jsonOut(['success' => true]);
}

function deleteProduct(PDO $db, int $id): void
{
    if ($id <= 0) jsonError('Некорректный идентификатор товара', 422);
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) jsonError('Товар не найден', 404);
    jsonOut(['success' => true]);
}
