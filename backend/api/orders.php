<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    getOrders($db);
} elseif ($method === 'POST' && $action === 'create') {
    createOrder($db);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Invalid request"]);
}

function getOrders($db) {
    $query = "SELECT * FROM orders ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    foreach ($orders as &$order) {
        $order['items'] = getOrderItems($db, $order['id']);
    }
    
    echo json_encode($orders);
}

function getOrderItems($db, $orderId) {
    $query = "SELECT oi.*, p.name as product_name FROM order_items oi 
              LEFT JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $orderId]);
    return $stmt->fetchAll();
}

function createOrder($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $db->beginTransaction();
        
        // Создаём заявку
        $query = "INSERT INTO orders (name, phone, email, comment, order_type, total_amount) 
                  VALUES (:name, :phone, :email, :comment, :order_type, :total_amount)";
        $stmt = $db->prepare($query);
        
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'] ?? '',
            ':comment' => $data['comment'] ?? '',
            ':order_type' => $data['order_type'] ?? 'purchase',
            ':total_amount' => $data['total_amount'] ?? 0
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Добавляем позиции
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, item_type, quantity, price) 
                                      VALUES (:oid, :pid, :type, :qty, :price)");
                $stmt->execute([
                    ':oid' => $orderId,
                    ':pid' => $item['product_id'] ?? null,
                    ':type' => $item['type'],
                    ':qty' => $item['quantity'] ?? 1,
                    ':price' => $item['price'] ?? 0
                ]);
            }
        }
        
        $db->commit();
        echo json_encode(["success" => true, "id" => $orderId]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(["error" => "Failed to create order: " . $e->getMessage()]);
    }
}
?>