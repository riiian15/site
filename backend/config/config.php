<?php
/**
 * Шаблон конфигурации. Скопируйте этот файл в config.php
 * и заполните реальными значениями. Файл config.php НЕ должен
 * попадать в git (см. .gitignore).
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'meduzi_db',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Секретный ключ для подписи CSRF-токенов. Сгенерируйте свой:
    // php -r "echo bin2hex(random_bytes(32));"
    'app_key' => '45430e40223cff8a84d75332ed3e4a61d06df87f0fa06fb9be86b61807235ccf',

    // Белый список origin'ов для CORS. '*' только для локальной разработки!
    'cors_allowed_origins' => [
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        // 'https://example.ru',   // боевой домен — раскомментировать при деплое
    ],

    // Ограничения rate-limit'а (ключ => [макс. запросов, окно в секундах])
    'rate_limits' => [
        'api_read'     => [120, 60],
        'order_create' => [10, 60],
        'auth_login'   => [8, 300],
        'upload'       => [20, 60],
    ],
];
