<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Поставки';
$activePage = 'supplies.php';
$pageScript = 'supplies.js';
$pageData = [
    'suppliers'      => SupplierRepository::options(),
    'paymentMethods' => Database::all('SELECT id, name FROM payment_methods ORDER BY id'),
    'nextNumber'     => SupplyRepository::nextInvoiceNumber(),
    'tab'            => ($_GET['tab'] ?? '') === 'history' ? 'history' : 'receive',
    'status'         => $_GET['status'] ?? '',
];

require __DIR__ . '/views/layout/header.php';
?>

<ul class="nav nav-pills mb-3 gap-2" role="tablist">
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabReceive" id="tabReceiveBtn">
        <i class="bi bi-box-arrow-in-down me-1"></i>Приход товара</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabHistory" id="tabHistoryBtn">
        <i class="bi bi-journal-text me-1"></i>Накладные</button></li>
</ul>

<div class="tab-content">
    <!-- ================= Приход ================= -->
    <div class="tab-pane fade" id="tabReceive">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-file-earmark-text"></i>Приходная накладная</div>
            <div class="card-body">
                <form id="headForm" class="row g-3">
                    <div class="col-md-4"><label class="form-label">Поставщик</label>
                        <div class="input-group">
                            <select class="form-select" name="supplier_id" required></select>
                            <a class="btn btn-outline-secondary" href="suppliers.php" title="Справочник поставщиков"><i class="bi bi-building-add"></i></a>
                        </div></div>
                    <div class="col-md-2"><label class="form-label">№ накладной</label>
                        <input class="form-control" name="invoice_number" maxlength="50"></div>
                    <div class="col-md-2"><label class="form-label">Дата</label>
                        <input type="date" class="form-control" name="supply_date" required></div>
                    <div class="col-md-2"><label class="form-label">Отсрочка, дн.</label>
                        <input type="number" class="form-control" name="payment_delay_days" min="0" max="365" value="0"></div>
                    <div class="col-md-2"><label class="form-label">Оплата</label>
                        <select class="form-select" name="payment_method_id"></select></div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="paid" id="paidSwitch">
                            <label class="form-check-label" for="paidSwitch">Накладная уже оплачена</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-list-check"></i>Состав накладной</div>
            <div class="card-body pb-0">
                <div class="position-relative">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" id="partSearch" placeholder="Найдите запчасть по артикулу или названию, чтобы добавить…" autocomplete="off">
                        <a class="btn btn-outline-secondary" href="catalog.php" title="Новая позиция заводится в каталоге"><i class="bi bi-plus-lg"></i> В каталог</a>
                    </div>
                    <div class="list-group position-absolute w-100 shadow" id="partResults" style="z-index:5; max-height:300px; overflow:auto"></div>
                </div>
            </div>
            <div class="table-responsive mt-3">
                <table class="table align-middle">
                    <thead><tr><th>Запчасть</th><th style="width:110px">Кол-во</th><th style="width:150px">Закупка, ₽</th>
                        <th style="width:150px">Продажа, ₽</th><th class="num">Наценка</th><th class="num">Сумма</th><th></th></tr></thead>
                    <tbody id="itemsBody"></tbody>
                    <tfoot><tr><td colspan="5" class="text-end">Итого по накладной:</td><td class="num" id="itemsTotal"></td><td></td></tr></tfoot>
                </table>
            </div>
            <div class="card-body d-flex justify-content-end gap-2">
                <button class="btn btn-light" id="clearItems">Очистить</button>
                <button class="btn btn-primary" id="submitSupply"><i class="bi bi-check2-circle me-1"></i>Провести накладную</button>
            </div>
        </div>
    </div>

    <!-- ================= История ================= -->
    <div class="tab-pane fade" id="tabHistory">
        <div class="row g-3 mb-3" id="statsRow"></div>

        <div class="card mb-3">
            <div class="card-body">
                <form id="historyFilters" class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label">Поставщик</label>
                        <select class="form-select" name="supplier_id"></select></div>
                    <div class="col-md-2"><label class="form-label">Оплата</label>
                        <select class="form-select" name="payment_status">
                            <option value="">Все</option><option value="unpaid">Не оплачены</option>
                            <option value="overdue">Просрочены</option><option value="paid">Оплачены</option>
                        </select></div>
                    <div class="col-md-2"><label class="form-label">С даты</label><input type="date" class="form-control" name="date_from"></div>
                    <div class="col-md-2"><label class="form-label">По дату</label><input type="date" class="form-control" name="date_to"></div>
                    <div class="col-md-3"><label class="form-label">№ накладной или деталь</label>
                        <input type="search" class="form-control" name="search" placeholder="Поиск…"></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>№</th><th>Дата</th><th>Поставщик</th><th class="text-center">Поз.</th>
                        <th class="num">Сумма</th><th class="num">Возвраты</th><th class="num">К оплате</th>
                        <th>Срок оплаты</th><th>Статус</th><th></th></tr></thead>
                    <tbody id="invoicesBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Детали накладной -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="invoiceTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="invoiceBody"></div>
        </div>
    </div>
</div>

<!-- Возврат поставщику -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="returnForm">
            <div class="modal-header"><h5 class="modal-title">Возврат поставщику</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="supply_id">
                <p class="mb-3" id="returnPart"></p>
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Количество</label>
                        <input type="number" class="form-control" name="quantity" min="1" required></div>
                    <div class="col-6"><label class="form-label">Дата возврата</label>
                        <input type="date" class="form-control" name="return_date" required></div>
                    <div class="col-12"><label class="form-label">Причина</label>
                        <input class="form-control" name="reason" maxlength="255" placeholder="Брак, пересорт, ошибка в заказе…"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-danger">Оформить возврат</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
