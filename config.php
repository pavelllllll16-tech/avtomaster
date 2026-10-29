<?php
// Настройки приложения. Для OpenServer 5 подходят значения по умолчанию.
// В OpenServer 6 вместо 'localhost' укажите имя модуля СУБД, например 'MySQL-8.0'.

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'auto_parts_db',
        'user'     => 'root',
        'password' => '',
    ],
    'app' => [
        'name'       => 'АвтоМастер',
        'shop_name'  => 'ИП Мелекесов',   // печатается на ценниках
        'currency'   => '₽',
        'timezone'   => 'Europe/Moscow',
        'debug'      => true,             // показывать текст ошибок БД (выключить на защите не обязательно)
    ],
];
