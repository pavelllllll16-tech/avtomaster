<?php
/**
 * Точка инициализации: подключается в начале каждой страницы и каждого API-файла.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

date_default_timezone_set(config('app')['timezone']);
mb_internal_encoding('UTF-8');

// Автозагрузка классов из app/ и app/models/
spl_autoload_register(function (string $class) {
    foreach ([__DIR__ . "/$class.php", __DIR__ . "/models/$class.php"] as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
