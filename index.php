<?php
require_once __DIR__ . '/app/bootstrap.php';

$stats = ReportRepository::dashboard();
$monthStart = date('Y-m-01');
$today = date('Y-m-d');
$lowStock = PartRepository::list(['stock' => 'low'], 6);
$outStock = PartRepository::list(['stock' => 'out'], 6);
$recent = ReportRepository::recentOrders(7);
$top = ReportRepository::topParts($monthStart, $today, 5);

$pageTitle = 'Главная';
$activePage = 'index.php';
$pageScript = 'dashboard.js';
$needCharts = true;
$pageData = [
    'salesByDay' => ReportRepository::salesByDay(30),
    'byCategory' => ReportRepository::revenueByCategory(date('Y-m-d', strtotime('-29 days')), $today),
];

$kpis = [
    ['Выручка сегодня',      money($stats['revenue_today']), 'cash-coin',       '',      null],
    ['Выручка за месяц',     money($stats['revenue_month']), 'graph-up-arrow',  'green', 'reports.php'],
    ['Прибыль за месяц',     money($stats['profit_month']),  'piggy-bank',      'blue',  'reports.php'],
    ['Средний чек',          money($stats['avg_check']),     'receipt-cutoff',  '',      null],
    ['Открытые заказы',      (int)$stats['open_orders'],     'hourglass-split', '',      'orders.php?status=0'],
    ['Мало на складе',       (int)$stats['low_stock'] . ' / нет: ' . (int)$stats['out_of_stock'], 'exclamation-triangle', 'red', 'catalog.php?stock=need'],
    ['Склад по закупке',     money($stats['stock_value']),   'boxes',           'blue',  null],
    ['Долг поставщикам',     money($stats['unpaid_amount']), 'truck',           (float)$stats['overdue_amount'] > 0 ? 'red' : 'green', 'supplies.php?tab=history&status=unpaid'],
];

require __DIR__ . '/views/layout/header.php';
?>

<div class="row g-3 mb-3">
    <?php foreach ($kpis as list($label, $value, $icon, $color, $link)): ?>
        <div class="col-sm-6 col-xl-3">
            <?php if ($link): ?><a class="kpi-link" href="<?= e($link) ?>"><?php endif; ?>
            <div class="card h-100">
                <div class="kpi">
                    <div class="kpi-icon <?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
                    <div class="overflow-hidden">
                        <div class="kpi-label"><?= e($label) ?></div>
                        <div class="kpi-value text-truncate"><?= e($value) ?></div>
                    </div>
                </div>
            </div>
            <?php if ($link): ?></a><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ((float)$stats['overdue_amount'] > 0): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-alarm fs-5"></i>
        <div>Просрочена оплата поставщикам на сумму <b><?= money($stats['overdue_amount']) ?></b>.
            <a href="supplies.php?tab=history&status=overdue" class="alert-link">Посмотреть накладные</a></div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up"></i>Выручка и прибыль за 30 дней</div>
            <div class="card-body"><canvas id="salesChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart"></i>Продажи по категориям</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if ($pageData['byCategory']): ?>
                    <canvas id="categoryChart" height="240"></canvas>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-pie-chart"></i>Нет продаж за 30 дней</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clock-history"></i>Последние заказы
                <a href="orders.php" class="ms-auto small fw-normal">Все заказы</a></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th class="num">Сумма</th><th>Статус</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr role="button" onclick="location.href='orders.php?id=<?= (int)$o['id'] ?>'">
                            <td class="fw-semibold"><?= (int)$o['id'] ?></td>
                            <td class="text-nowrap"><?= e(date('d.m H:i', strtotime($o['created_at']))) ?></td>
                            <td><?= e($o['customer_name'] ?: '—') ?></td>
                            <td class="num"><?= money($o['final_amount']) ?></td>
                            <td><span class="badge-soft <?= ['warning', 'success', 'muted'][$o['status']] ?>"><?= OrderRepository::STATUS_NAMES[$o['status']] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">Заказов пока нет</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-trophy"></i>Топ продаж месяца</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($top as $i => $t): ?>
                    <li class="list-group-item bg-transparent d-flex align-items-center gap-3">
                        <span class="badge rounded-pill bg-accent-soft text-accent"><?= $i + 1 ?></span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate fw-semibold"><?= e($t['name']) ?></div>
                            <small class="text-secondary"><?= e($t['code']) ?> · продано <?= (int)$t['sold'] ?> шт.</small>
                        </div>
                        <span class="num fw-semibold"><?= money($t['revenue']) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$top): ?>
                    <li class="list-group-item bg-transparent text-center text-secondary py-4">В этом месяце продаж ещё не было</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-exclamation-triangle"></i>Требуют закупки</div>
            <ul class="list-group list-group-flush">
                <?php foreach (array_merge($outStock, $lowStock) as $p): ?>
                    <li class="list-group-item bg-transparent">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-truncate"><span class="stock-dot <?= $p['stock_state'] ?>"></span><?= e($p['name']) ?></span>
                            <span class="fw-semibold text-nowrap"><?= (int)$p['quantity'] ?> шт.</span>
                        </div>
                        <small class="text-secondary"><?= e($p['code']) ?> · мин. <?= (int)$p['min_quantity'] ?></small>
                    </li>
                <?php endforeach; ?>
                <?php if (!$outStock && !$lowStock): ?>
                    <li class="list-group-item bg-transparent text-center text-secondary py-4">Все позиции в наличии</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
