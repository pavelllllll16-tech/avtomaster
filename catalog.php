<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Склад и каталог';
$activePage = 'catalog.php';
$pageScript = 'catalog.js';
$pageData = ['stock' => $_GET['stock'] ?? 'all'];

require __DIR__ . '/views/layout/header.php';
?>

<div class="row g-3">
    <!-- Дерево категорий -->
    <div class="col-lg-4 col-xl-3">
        <div class="card">
            <div class="card-header"><i class="bi bi-diagram-3"></i>Категории
                <button class="btn btn-sm btn-outline-primary ms-auto" id="addCategory" title="Добавить категорию"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div class="card-body p-2">
                <ul class="tree" id="tree"></ul>
            </div>
        </div>
    </div>

    <!-- Запчасти -->
    <div class="col-lg-8 col-xl-9">
        <div class="card">
            <div class="card-header flex-wrap"><i class="bi bi-box-seam"></i><span id="listTitle">Все запчасти</span>
                <span class="text-secondary fw-normal small" id="listCount"></span>
                <div class="ms-auto d-flex gap-2">
                    <input type="search" class="form-control form-control-sm" id="search" placeholder="Артикул или название" style="width:220px">
                    <select class="form-select form-select-sm" id="stockFilter" style="width:auto">
                        <option value="all">Все остатки</option>
                        <option value="in">В наличии</option>
                        <option value="need">Требуют закупки</option>
                        <option value="low">Заканчиваются</option>
                        <option value="out">Нет в наличии</option>
                    </select>
                    <button class="btn btn-sm btn-primary text-nowrap" id="addPart"><i class="bi bi-plus-lg me-1"></i>Запчасть</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr><th>Артикул</th><th>Наименование</th><th>Категория</th><th>Остаток</th>
                        <th class="num">Закупка</th><th class="num">Продажа</th><th class="num">Наценка</th>
                        <th></th></tr>
                    </thead>
                    <tbody id="partsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Запчасть -->
<div class="modal fade" id="partModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="partForm">
            <div class="modal-header"><h5 class="modal-title" id="partModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Категория</label>
                        <select class="form-select" id="partCategory" required></select></div>
                    <div class="col-md-6"><label class="form-label">Подкатегория</label>
                        <select class="form-select" name="subcategory_id" id="partSubcategory" required></select></div>
                    <div class="col-md-4"><label class="form-label">Артикул</label>
                        <input class="form-control" name="code" maxlength="50" required></div>
                    <div class="col-md-8"><label class="form-label">Наименование</label>
                        <input class="form-control" name="name" maxlength="200" required></div>
                    <div class="col-md-3"><label class="form-label">Остаток, шт.</label>
                        <input type="number" class="form-control" name="quantity" min="0" value="0" required></div>
                    <div class="col-md-3"><label class="form-label">Мин. остаток</label>
                        <input type="number" class="form-control" name="min_quantity" min="0" value="2" required
                               data-bs-toggle="tooltip" title="При остатке ниже этого значения позиция попадёт в «Требуют закупки»"></div>
                    <div class="col-md-3"><label class="form-label">Закупка, ₽</label>
                        <input type="number" class="form-control" name="purchase_price" min="0" step="0.01" required></div>
                    <div class="col-md-3"><label class="form-label">Продажа, ₽</label>
                        <input type="number" class="form-control" name="selling_price" min="0" step="0.01" required></div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded bg-accent-soft">
                            <span class="small">Наценка:</span><b id="markupView">—</b>
                            <span class="small ms-3">Быстрая наценка:</span>
                            <?php foreach ([20, 30, 40, 50] as $m): ?>
                                <button type="button" class="btn btn-sm btn-light quick-markup" data-m="<?= $m ?>">+<?= $m ?>%</button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Категория / подкатегория -->
<div class="modal fade" id="nameModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" id="nameForm">
            <div class="modal-header"><h5 class="modal-title" id="nameModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id"><input type="hidden" name="category_id"><input type="hidden" name="kind">
                <label class="form-label">Название</label>
                <input class="form-control" name="name" maxlength="100" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
