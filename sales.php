<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Продажа';
$activePage = 'sales.php';
$pageScript = 'sales.js';
$pageData = [
    'categories'     => CategoryRepository::tree(),
    'paymentMethods' => Database::all('SELECT id, name FROM payment_methods ORDER BY id'),
];

require __DIR__ . '/views/layout/header.php';
?>

<div class="pos">
    <!-- Подбор товара -->
    <div>
        <div class="card mb-3">
            <div class="card-body">
                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="search" placeholder="Поиск по артикулу или названию…" autofocus>
                </div>
                <div class="chips mb-2" id="categoryChips"></div>
                <div class="chips" id="subcategoryChips"></div>
            </div>
        </div>
        <div id="products" class="product-grid"></div>
    </div>

    <!-- Корзина -->
    <div class="card pos-cart">
        <div class="card-header"><i class="bi bi-cart3"></i>Корзина
            <span class="badge rounded-pill text-bg-primary ms-1" id="cartCount">0</span>
            <button class="btn btn-sm btn-link text-danger ms-auto p-0" id="clearCart">Очистить</button>
        </div>
        <div class="card-body pt-0">
            <div id="cartLines"></div>

            <hr>
            <div class="row g-2 mb-2">
                <div class="col-12">
                    <input class="form-control" id="customerName" maxlength="150" placeholder="Покупатель (необязательно)">
                </div>
                <div class="col-7">
                    <select class="form-select" id="paymentMethod"></select>
                </div>
                <div class="col-5">
                    <div class="input-group">
                        <input type="number" class="form-control" id="orderDiscount" min="0" max="100" step="0.5" value="0">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12">
                    <input class="form-control" id="notes" maxlength="1000" placeholder="Примечание">
                </div>
            </div>

            <div class="d-flex justify-content-between text-secondary small"><span>Сумма позиций</span><span id="subtotal"></span></div>
            <div class="d-flex justify-content-between text-secondary small"><span>Скидка на заказ</span><span id="discountAmount"></span></div>
            <div class="d-flex justify-content-between align-items-end mt-2">
                <span class="fw-semibold">Итого к оплате</span>
                <span class="cart-total" id="total"></span>
            </div>
            <button class="btn btn-primary btn-lg w-100 mt-3 fw-semibold" id="checkout">
                <i class="bi bi-check2-circle me-1"></i>Оформить продажу
            </button>
        </div>
    </div>
</div>

<!-- Окно после оформления -->
<div class="modal fade" id="doneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-check-circle-fill text-success display-4"></i>
                <h5 class="mt-3" id="doneTitle"></h5>
                <p class="text-secondary mb-0" id="doneSum"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button class="btn btn-light" id="printReceipt"><i class="bi bi-printer me-1"></i>Печать чека</button>
                <button class="btn btn-primary" data-bs-dismiss="modal">Новая продажа</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
