<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Поставщики';
$activePage = 'suppliers.php';
$pageScript = 'suppliers.js';

require __DIR__ . '/views/layout/header.php';
?>

<div class="card">
    <div class="card-header"><i class="bi bi-building"></i>Справочник поставщиков
        <input type="search" class="form-control form-control-sm ms-auto" id="search" placeholder="Поиск…" style="width:220px">
        <button class="btn btn-primary btn-sm text-nowrap" id="addSupplier"><i class="bi bi-plus-lg me-1"></i>Поставщик</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Название</th><th>Контакты</th><th class="text-center">Накладных</th>
                <th>Последняя поставка</th><th class="num">Закуплено</th><th class="num">Долг</th><th></th></tr></thead>
            <tbody id="suppliersBody"></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="supplierForm">
            <div class="modal-header"><h5 class="modal-title" id="supplierTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <div class="mb-3"><label class="form-label">Название</label>
                    <input class="form-control" name="name" maxlength="150" required></div>
                <div><label class="form-label">Контакты</label>
                    <textarea class="form-control" name="contact_info" maxlength="255" rows="2" placeholder="Телефон, e-mail, адрес"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
