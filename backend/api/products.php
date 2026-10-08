<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'categories') {
            getCategories($db);
        } elseif ($action === 'product') {
            getProduct($db, $_GET['id'] ?? 0);
        } else {
            getProducts($db);
        }
        break;
    
    case 'POST':
        if ($action === 'create') {
            createProduct($db);
        }
        break;
    
    case 'PUT':
        if ($action === 'update') {
            updateProduct($db, $_GET['id'] ?? 0);
        }
        break;
    
    case 'DELETE':
        if ($action === 'delete') {
            deleteProduct($db, $_GET['id'] ?? 0);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function getCategories($db) {
    $query = "SELECT * FROM categories ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll();
    echo json_encode($categories);
}

function getProducts($db) {
    $filters = [];
    $params = [];
    
    if (isset($_GET['category'])) {
        $filters[] = "p.category_id = :category";
        $params[':category'] = $_GET['category'];
    }
    
    if (isset($_GET['condition'])) {
    $filters[] = "p.product_condition = :product_condition";
    $params[':product_condition'] = $_GET['condition'];
    }
    
    if (isset($_GET['brand'])) {
        $filters[] = "p.brand = :brand";
        $params[':brand'] = $_GET['brand'];
    }
    
    if (isset($_GET['yearFrom'])) {
        $filters[] = "p.year >= :yearFrom";
        $params[':yearFrom'] = $_GET['yearFrom'];
    }
    
    if (isset($_GET['yearTo'])) {
        $filters[] = "p.year <= :yearTo";
        $params[':yearTo'] = $_GET['yearTo'];
    }
    
    if (isset($_GET['priceFrom'])) {
        $filters[] = "p.price >= :priceFrom";
        $params[':priceFrom'] = $_GET['priceFrom'];
    }
    
    if (isset($_GET['priceTo'])) {
        $filters[] = "p.price <= :priceTo";
        $params[':priceTo'] = $_GET['priceTo'];
    }
    
    if (isset($_GET['search'])) {
        $filters[] = "(p.name LIKE :search OR p.description LIKE :search2 OR p.brand LIKE :search3)";
        $params[':search'] = '%' . $_GET['search'] . '%';
        $params[':search2'] = '%' . $_GET['search'] . '%';
        $params[':search3'] = '%' . $_GET['search'] . '%';
    }
    
    $where = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";
    
    $query = "SELECT p.*, c.name as category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              $where ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Добавляем характеристики и изображения
    foreach ($products as &$product) {
        $product['specs'] = getProductSpecs($db, $product['id']);
        $product['images'] = getProductImages($db, $product['id']);
    }
    
    echo json_encode($products);
}

function getProduct($db, $id) {
    $query = "SELECT p.*, c.name as category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(["error" => "Product not found"]);
        return;
    }
    
    $product['specs'] = getProductSpecs($db, $id);
    $product['images'] = getProductImages($db, $id);
    
    echo json_encode($product);
}

function getProductSpecs($db, $productId) {
    $query = "SELECT spec_name, spec_value FROM product_specs WHERE product_id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $productId]);
    $specs = $stmt->fetchAll();
    
    $result = [];
    foreach ($specs as $spec) {
        $result[$spec['spec_name']] = $spec['spec_value'];
    }
    return $result;
}

function getProductImages($db, $productId) {
    $query = "SELECT image_path FROM product_images WHERE product_id = :id ORDER BY sort_order";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $productId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function createProduct($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $query = "INSERT INTO products (name, brand, year, price, product_condition, category_id, description) 
            VALUES (:name, :brand, :year, :price, :product_condition, :category_id, :description)";
    $stmt = $db->prepare($query);

    $stmt->execute([
        ':name' => $data['name'],
        ':brand' => $data['brand'],
        ':year' => $data['year'],
        ':price' => $data['price'],
        ':product_condition' => $data['condition'],
        ':category_id' => $data['category_id'],
        ':description' => $data['description'] ?? ''
    ]);
    
    $productId = $db->lastInsertId();
    
    // Добавляем характеристики
    if (isset($data['specs'])) {
        foreach ($data['specs'] as $name => $value) {
            $stmt = $db->prepare("INSERT INTO product_specs (product_id, spec_name, spec_value) VALUES (:pid, :name, :value)");
            $stmt->execute([':pid' => $productId, ':name' => $name, ':value' => $value]);
        }
    }
    
    echo json_encode(["success" => true, "id" => $productId]);
}

function updateProduct($db, $id) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $query = "UPDATE products SET name = :name, brand = :brand, year = :year, 
            price = :price, product_condition = :product_condition, category_id = :category_id, 
            description = :description WHERE id = :id";
    $stmt = $db->prepare($query);

    $stmt->execute([
        ':id' => $id,
        ':name' => $data['name'],
        ':brand' => $data['brand'],
        ':year' => $data['year'],
        ':price' => $data['price'],
        ':product_condition' => $data['condition'],
        ':category_id' => $data['category_id'],
        ':description' => $data['description'] ?? ''
    ]);
    
    echo json_encode(["success" => true]);
}

function deleteProduct($db, $id) {
    $query = "DELETE FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    
    echo json_encode(["success" => true]);
}
?>