<?php
/**
 * Загрузка изображений товаров (multipart/form-data, поле "image").
 * Улучшения: проверка MIME по содержимому, повторное кодирование через
 * imagecreatefrom* (убивает встроенные payload), лимиты размера,
 * случайные имена файлов, авторизация + CSRF + rate-limit.
 */

require_once __DIR__ . '/middleware/helpers.php';

handleCors();
startSecureSession();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonError('Требуется POST', 405);
}
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true)) {
    jsonError('Требуется авторизация', 401);
}
verifyCsrf();
enforceRateLimit('upload');

$maxSize = 5 * 1024 * 1024; // 5 МБ
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonError('Файл не загружен', 422);
}
if ($file['size'] > $maxSize) {
    jsonError('Максимальный размер файла — 5 МБ', 413);
}

// Определяем тип по реальному содержимому, а не по расширению
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!isset($allowedMime[$mime])) {
    jsonError('Допустимы только JPG, PNG и WebP', 422);
}

$uploadDir = __DIR__ . '/../../assets/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    @file_put_contents("$uploadDir/.htaccess", "Require all denied\n");
}
@file_put_contents("$uploadDir/.htaccess", "php_flag engine off\nAddType text/plain .php .phtml .php5\nOptions -ExecCGI\n");

$name = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];
$dest = "$uploadDir/$name";

// Пересохраняем изображение — нейтрализует любой payload внутри файла
$img = match ($mime) {
    'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
    'image/png'  => @imagecreatefrompng($file['tmp_name']),
    'image/webp' => @imagecreatefromwebp($file['tmp_name']),
    default      => false,
};
if ($img === false) {
    jsonError('Не удалось обработать изображение', 422);
}

// Ограничиваем разрешение (экономия места)
$maxDim = 1920;
$w = imagesx($img); $h = imagesy($img);
if ($w > $maxDim || $h > $maxDim) {
    $scale = min($maxDim / $w, $maxDim / $h);
    $resized = imagescale($img, (int)($w * $scale), (int)($h * $scale));
    if ($resized) { imagedestroy($img); $img = $resized; }
}

$ok = match ($mime) {
    'image/jpeg' => imagejpeg($img, $dest, 85),
    'image/png'  => imagepng($img, $dest, 8),
    'image/webp' => imagewebp($img, $dest, 85),
};
imagedestroy($img);

if (!$ok) {
    jsonError('Ошибка сохранения файла', 500);
}

jsonOut(['success' => true, 'path' => '/site/backend/assets/uploads/' . $name], 201);
