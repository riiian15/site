<?php
/**
 * Общие хелперы API: JSON-ответы, CORS по белому списку,
 * rate limiting, CSRF, валидация, безопасный session_start.
 */

require_once __DIR__ . '/../../config/database.php';

/* ---------- JSON / HTTP ---------- */

function jsonOut($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void
{
    jsonOut(['error' => $message], $code);
}

/** Читает и парсит JSON-тело запроса. */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* ---------- Безопасный старт сессии ---------- */

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                       || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('MEDUZI_SESSID');
    session_start();
}

/* ---------- CORS ---------- */

function handleCors(): void
{
    $cfg    = Database::getAppConfig();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $cfg['cors_allowed_origins'] ?? [];

    if ($origin !== '') {
        if (in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        }
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/* ---------- Rate limiting (файловый sliding-window) ---------- */

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * @return bool true — запрос разрешён, false — лимит превышен.
 */
function rateLimit(string $bucket, int $maxRequests, int $windowSec): bool
{
    $dir = sys_get_temp_dir() . '/meduzi_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $key   = $dir . '/' . preg_replace('/[^a-z0-9_]/i', '', $bucket)
                      . '_' . md5(clientIp() . '|' . ($_SERVER['PHP_SELF'] ?? ''));
    $now   = time();
    $hits  = [];

    if (is_file($key)) {
        $raw = @file_get_contents($key);
        foreach (explode("\n", (string)$raw) as $line) {
            $t = (int)$line;
            if ($t > $now - $windowSec) {
                $hits[] = $t;
            }
        }
    }

    if (count($hits) >= $maxRequests) {
        return false;
    }

    $hits[] = $now;
    @file_put_contents($key, implode("\n", $hits));
    return true;
}

function enforceRateLimit(string $bucket): void
{
    $cfg = Database::getAppConfig();
    [$max, $window] = $cfg['rate_limits'][$bucket] ?? [60, 60];
    if (!rateLimit($bucket, (int)$max, (int)$window)) {
        header('Retry-After: ' . $window);
        jsonError('Слишком много запросов. Повторите позже.', 429);
    }
}

/* ---------- CSRF ---------- */

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Проверяет токен для изменяющих запросов (POST/PUT/DELETE). */
function verifyCsrf(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        startSecureSession();
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] !== '' ? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') : '';
        if ($sent === '') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            $sent = is_array($body) && isset($body['csrf']) ? (string)$body['csrf'] : '';
        }
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$sent)) {
            jsonError('Недействительный CSRF-токен', 403);
        }
    }
}

/* ---------- Валидация ---------- */

function need(array $data, string ...$fields): void
{
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            jsonError("Поле «{$f}» обязательно для заполнения", 422);
        }
    }
}

function textInRange(string $s, int $min, int $max): bool
{
    $len = mb_strlen(trim($s));
    return $len >= $min && $len <= $max;
}

function validPhone(string $p): bool
{
    return (bool)preg_match('/^\+?[\d\s\-\(\)]{7,20}$/', $p);
}

function sanitizeText(string $s, int $max = 1000): string
{
    $s = trim(strip_tags($s));
    return mb_substr($s, 0, $max);
}

/** Белые списки для enum-полей. */
function oneOf($value, array $allowed, string $fieldName): string
{
    if (!in_array($value, $allowed, true)) {
        jsonError("Недопустимое значение поля «{$fieldName}»", 422);
    }
    return $value;
}

/** Безопасная пагинация. */
function pagination(int $defPerPage = 24, int $maxPerPage = 100): array
{
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min($maxPerPage, max(1, (int)($_GET['per_page'] ?? $defPerPage)));
    return [$page, $perPage, ($page - 1) * $perPage];
}
