<?php
/**
 * Общая шапка всех страниц: боковое меню, верхняя панель.
 * Перед подключением задать: $pageTitle, $activePage, при необходимости $pageScript и $pageData.
 */
$app = config('app');
$menu = [
    // файл,           иконка,           название
    ['index.php',     'speedometer2',   'Главная'],
    ['catalog.php',   'box-seam',       'Склад и каталог'],
    ['sales.php',     'cart3',          'Продажа'],
    ['orders.php',    'receipt',        'Заказы'],
    ['supplies.php',  'truck',          'Поставки'],
    ['suppliers.php', 'building',       'Поставщики'],
    ['reports.php',   'bar-chart-line', 'Отчёты'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= e($app['name']) ?></title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <script>
        // Тема применяется до отрисовки, чтобы не было «мигания»
        try { document.documentElement.dataset.bsTheme = localStorage.getItem('theme') || 'light'; } catch (e) {}
    </script>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="index.php">
            <img src="assets/img/logo.svg" alt="" width="34" height="34">
            <span><?= e($app['name']) ?></span>
        </a>
        <nav class="sidebar-nav">
            <?php foreach ($menu as list($file, $icon, $title)): ?>
                <a href="<?= $file ?>" class="sidebar-link <?= $activePage === $file ? 'active' : '' ?>">
                    <i class="bi bi-<?= $icon ?>"></i><span><?= $title ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <main class="main">
        <header class="topbar">
            <button class="btn btn-icon d-lg-none" id="sidebarToggle" aria-label="Меню"><i class="bi bi-list fs-4"></i></button>
            <h1 class="page-title"><?= e($pageTitle) ?></h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="text-secondary small d-none d-md-inline" id="clock"></span>
                <button class="btn btn-icon" id="themeToggle" title="Светлая / тёмная тема"><i class="bi bi-moon-stars"></i></button>
            </div>
        </header>
        <div class="content">
