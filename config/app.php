<?php

return [
    'name' => env_value('APP_NAME', 'IIS-TJT Movers'),
    'base_url' => env_value('APP_BASE_URL', ''),
    'timezone' => env_value('APP_TIMEZONE', 'Asia/Manila'),
    'environment' => env_value('APP_ENV', 'local'),
    'database' => [
        'host' => env_value('DB_HOST', '127.0.0.1'),
        'port' => (int) env_value('DB_PORT', 3306),
        'database' => env_value('DB_DATABASE', 'iis_tjt_db'),
        'username' => env_value('DB_USERNAME', 'root'),
        'password' => env_value('DB_PASSWORD', ''),
        'charset' => env_value('DB_CHARSET', 'utf8mb4'),
    ],
    'default_admin' => [
        'username' => env_value('DEFAULT_ADMIN_USERNAME', 'admin'),
        'password' => env_value('DEFAULT_ADMIN_PASSWORD', 'TJTAdmin@2026!'),
        'name' => env_value('DEFAULT_ADMIN_NAME', 'System Administrator'),
        'email' => env_value('DEFAULT_ADMIN_EMAIL', 'admin@tjtmovers.local'),
    ],
];
