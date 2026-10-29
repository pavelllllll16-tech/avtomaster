<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Отчёты';
$activePage = 'reports.php';
$pageScript = 'reports.js';
$needCharts = true;
$pageData = ['categories' => CategoryRepository::tree()];

require __DIR__ . '/views/layout/header.php';
?>

<div class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div><label class="form-label">С даты</label><input type="date" class="form-control" id="dateFrom"></div>
        <div><label class="form-label">По дату</label><input type="date" class="form-control" id="dateTo"></div>
        <div class="btn-group" role="group">
            <button class="btn btn-light period" data-p="today">Сегодня</button>
            <button class="btn btn-light period" data-p="week">7 дней</button>
            <button class="btn btn-light period" data-p="month">Этот месяц</button>
            <button class="btn btn-light period" data-p="prev">Прошлый месяц</button>
            <button class="btn btn-light period" data-p="year">Год</button>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-outline-secondary" id="exportCsv"><i class="bi bi-filetype-csv me-1"></i>В Excel</button>
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Печать</button>
        </div>
    </div>
</div>

<ul class="nav nav-pills mb-3 gap-2 no-print" id="reportTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabSales" data-report="sales"><i class="bi bi-receipt me-1"></i>Продажи</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabProfit" data-report="profit"><i class="bi bi-cash-stack me-1"></i>Прибыль</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabStock" data-report="stock"><i class="bi bi-boxes me-1"></i>Склад</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabTags" data-report="tags"><i class="bi bi-tags me-1"></i>Ценники</button></li>
</ul>

<h4 class="print-only mb-3" id="printTitle"></h4>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tabSales">
        <div class="row g-3 mb-3" id="salesKpi"></div>
        <div class="card"><div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th>Состав</th><th>Оплата</th>
                    <th class="num">Сумма</th><th class="num">Прибыль</th></tr></thead>
                <tbody id="salesBody"></tbody><tfoot id="salesFoot"></tfoot>
            </table>
        </div></div>
    </div>

    <div class="tab-pane fade" id="tabProfit">
        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <div class="card h-100"><div class="card-header"><i class="bi bi-diagram-3"></i>Прибыль по категориям</div>
                    <div class="table-responsive"><table class="table align-middle">
                        <thead><tr><th>Категория</th><th>Подкатегория</th><th class="num">Продано, шт.</th><th class="num">Выручка</th>
                            <th class="num">Себестоимость</th><th class="num">Прибыль</th><th class="num">Маржа</th></tr></thead>
                        <tbody id="profitBody"></tbody><tfoot id="profitFoot"></tfoot>
                    </table></div></div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-header"><i class="bi bi-pie-chart"></i>Доля выручки</div>
                    <div class="card-body d-flex align-items-center justify-content-center" id="profitChartWrap"><canvas id="profitChart" height="260"></canvas></div></div>
            </div>
        </div>
        <div class="card"><div class="card-header"><i class="bi bi-trophy"></i>Топ-10 запчастей по выручке</div>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>#</th><th>Артикул</th><th>Наименование</th><th class="num">Продано, шт.</th><th class="num">Выручка</th></tr></thead>
                <tbody id="topBody"></tbody>
            </table></div></div>
    </div>

    <div class="tab-pane fade" id="tabStock">
        <div class="card"><div class="card-header"><i class="bi bi-boxes"></i>Остатки на складе (на текущий момент)</div>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>Категория</th><th>Подкатегория</th><th class="num">Позиций</th><th class="num">Штук</th>
                    <th class="num">По закупке</th><th class="num">По продаже</th><th class="num">Потенц. прибыль</th><th class="num">Ср. наценка</th></tr></thead>
                <tbody id="stockBody"></tbody><tfoot id="stockFoot"></tfoot>
            </table></div></div>
    </div>

    <div class="tab-pane fade" id="tabTags">
        <div class="card">
            <div class="card-header flex-wrap"><i class="bi bi-tags"></i>Печать ценников
                <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="tagCategory" style="width:auto"></select>
                    <input type="search" class="form-control form-control-sm" id="tagSearch" placeholder="Поиск…" style="width:200px">
                    <button class="btn btn-sm btn-primary text-nowrap" id="printTags" disabled><i class="bi bi-printer me-1"></i>Печать (<span id="tagCount">0</span>)</button>
                </div>
            </div>
            <div class="table-responsive" style="max-height:60vh">
                <table class="table table-hover align-middle">
                    <thead><tr><th style="width:40px"><input type="checkbox" class="form-check-input" id="tagAll"></th>
                        <th>Артикул</th><th>Наименование</th><th>Категория</th><th class="num">Цена</th><th class="text-center">Остаток</th></tr></thead>
                    <tbody id="tagsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
