<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    login($db);
} elseif ($action === 'logout') {
    logout();
} elseif ($action === 'check') {
    checkAuth();
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid action"]);
}

function login($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $query = "SELECT * FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $data['username']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($data['password'], $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "role" => $user['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid credentials"]);
    }
}

function logout() {
    session_destroy();
    echo json_encode(["success" => true]);
}

function checkAuth() {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            "authenticated" => true,
            "user" => [
                "id" => $_SESSION['user_id'],
                "username" => $_SESSION['username'],
                "role" => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(["authenticated" => false]);
    }
}
?>