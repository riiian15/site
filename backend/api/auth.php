<?php
/**
 * Auth API: login / logout / check / csrf-token.
 * Улучшения: rate-limit на вход, session_regenerate_id против фиксации
 * сессии, выдача CSRF-токена, безопасные cookie-сессии, без утечки деталей.
 */

require_once __DIR__ . '/middleware/helpers.php';

handleCors();
startSecureSession();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        enforceRateLimit('auth_login');
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'check':
        checkAuth();
        break;
    case 'csrf-token':
        jsonOut(['csrf' => csrfToken()]);
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

function login(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonError('Требуется POST', 405);
    }

    $data = readJsonBody();
    need($data, 'username', 'password');

    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :u");
    $stmt->execute([':u' => sanitizeText($data['username'], 50)]);
    $user = $stmt->fetch();

    // always-run hash, чтобы не светить существование пользователя по времени ответа
    $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvali';
    if ($user && password_verify($data['password'], $hash)) {
        // защита от session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Обновление хеша, если использовался устаревший алгоритм
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
               ->execute([':h' => password_hash($data['password'], PASSWORD_DEFAULT), ':id' => $user['id']]);
        }

        jsonOut([
            'success' => true,
            'csrf'    => $_SESSION['csrf_token'],
            'user'    => [
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ],
        ]);
    }

    jsonError('Неверный логин или пароль', 401);
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    jsonOut(['success' => true]);
}

function checkAuth(): void
{
    if (!empty($_SESSION['user_id'])) {
        jsonOut([
            'authenticated' => true,
            'csrf'          => $_SESSION['csrf_token'] ?? csrfToken(),
            'user'          => [
                'id'       => (int)$_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role'     => $_SESSION['role'],
            ],
        ]);
    }
    jsonOut(['authenticated' => false, 'csrf' => csrfToken()]);
}
