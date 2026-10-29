<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Заказы';
$activePage = 'orders.php';
$pageScript = 'orders.js';
$pageData = [
    'paymentMethods' => Database::all('SELECT id, name FROM payment_methods ORDER BY id'),
    'openId'         => (int)($_GET['id'] ?? 0),
];
$status = $_GET['status'] ?? '';

require __DIR__ . '/views/layout/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form id="filters" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">№, клиент, примечание</label>
                <input type="search" class="form-control" name="search" placeholder="Поиск…">
            </div>
            <div class="col-md-3">
                <label class="form-label">Деталь в заказе</label>
                <input type="search" class="form-control" name="part" placeholder="Артикул или название">
            </div>
            <div class="col-md-2">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <option value="">Все</option>
                    <?php foreach (OrderRepository::STATUS_NAMES as $k => $name): ?>
                        <option value="<?= $k ?>" <?= (string)$k === $status ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">С даты</label>
                <input type="date" class="form-control" name="date_from">
            </div>
            <div class="col-md-2">
                <label class="form-label">По дату</label>
                <input type="date" class="form-control" name="date_to">
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-receipt"></i>Список заказов
        <span class="text-secondary fw-normal small" id="total"></span>
        <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#newOrderModal">
            <i class="bi bi-plus-lg me-1"></i>Новый заказ
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr><th>№</th><th>Дата</th><th>Клиент</th><th class="text-center">Товаров</th>
                <th>Оплата</th><th class="num">Сумма</th><th>Статус</th></tr>
            </thead>
            <tbody id="ordersBody"></tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end">
        <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
    </div>
</div>

<!-- Карточка заказа -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="orderPanel" style="width: min(760px, 100vw)">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="orderTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="orderBody"></div>
</div>

<!-- Новый заказ -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="newOrderForm">
            <div class="modal-header"><h5 class="modal-title">Новый заказ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-secondary small">Открытый заказ удобен, когда клиент ждёт поставку или собирает заказ частями.
                    Для обычной продажи используйте <a href="sales.php">кассу</a>.</p>
                <label class="form-label">Клиент</label>
                <input class="form-control" name="customer_name" maxlength="150" placeholder="ФИО или телефон">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-primary">Создать</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
