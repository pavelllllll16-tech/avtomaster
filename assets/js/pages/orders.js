/**
 * Заказы: список, фильтры, карточка заказа с редактированием.
 */
$(function () {
    const { esc, money, api, dateTime } = App;
    const panel = new bootstrap.Offcanvas('#orderPanel');
    let page = 1;
    let current = null;   // открытый в панели заказ

    // ---------- список ----------
    function loadList() {
        const filters = App.formData($('#filters'));
        $('#ordersBody').addClass('loading');
        api('orders', 'list', Object.assign({ page }, filters), { silent: true }).then(res => {
            $('#total').text('· найдено ' + res.total);
            if (!res.rows.length) {
                $('#ordersBody').html(`<tr><td colspan="7">${App.emptyState('receipt', 'Заказы не найдены')}</td></tr>`);
            } else {
                $('#ordersBody').html(res.rows.map(o => `
                    <tr role="button" data-id="${o.id}">
                        <td class="fw-semibold">${o.id}</td>
                        <td class="text-nowrap">${dateTime(o.created_at)}</td>
                        <td>${esc(o.customer_name || '—')}</td>
                        <td class="text-center">${o.items_count}</td>
                        <td>${esc(o.payment_method || '—')}</td>
                        <td class="num fw-semibold">${money(o.final_amount)}</td>
                        <td>${App.orderStatusBadge(o.status)}</td>
                    </tr>`).join(''));
            }
            renderPagination(res.page, res.pages);
        }).finally(() => $('#ordersBody').removeClass('loading'));
    }

    function renderPagination(cur, pages) {
        page = cur;
        if (pages <= 1) { $('#pagination').empty(); return; }
        let html = '';
        const item = (p, label, disabled, active) =>
            `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${label}</a></li>`;
        html += item(cur - 1, '‹', cur === 1);
        for (let p = 1; p <= pages; p++) {
            if (p === 1 || p === pages || Math.abs(p - cur) <= 2) html += item(p, p, false, p === cur);
            else if (Math.abs(p - cur) === 3) html += item(p, '…', true);
        }
        html += item(cur + 1, '›', cur === pages);
        $('#pagination').html(html);
    }

    $('#pagination').on('click', 'a', function (e) {
        e.preventDefault();
        page = Number($(this).data('page'));
        loadList();
    });
    $('#filters').on('input change', App.debounce(() => { page = 1; loadList(); }, 300))
                 .on('submit', e => e.preventDefault());
    $('#ordersBody').on('click', 'tr[data-id]', function () { openOrder($(this).data('id')); });

    // ---------- новый заказ ----------
    $('#newOrderForm').on('submit', function (e) {
        e.preventDefault();
        api('orders', 'create_open', App.formData($(this))).then(res => {
            bootstrap.Modal.getInstance('#newOrderModal').hide();
            this.reset();
            loadList();
            showOrder(res.order);
        });
    });

    // ---------- карточка заказа ----------
    function openOrder(id) {
        api('orders', 'get', { id }, { silent: true }).then(res => showOrder(res.order));
    }

    function showOrder(o) {
        current = o;
        const open = Number(o.status) === 0;
        $('#orderTitle').html(`Заказ № ${o.id} ${App.orderStatusBadge(o.status)}`);

        const itemsHtml = o.items.length ? o.items.map(it => `
            <tr data-item="${it.id}">
                <td><div class="fw-semibold">${esc(it.name)}</div><small class="text-secondary">${esc(it.code)}</small></td>
                <td style="width:90px">${open
                    ? `<input type="number" class="form-control form-control-sm item-qty" min="1" value="${it.quantity}">`
                    : it.quantity}</td>
                <td class="num">${money(it.price)}</td>
                <td style="width:90px">${open
                    ? `<input type="number" class="form-control form-control-sm item-discount" min="0" max="100" value="${Number(it.discount)}">`
                    : (Number(it.discount) ? Number(it.discount) + '%' : '—')}</td>
                <td class="num fw-semibold">${money(it.total_price)}</td>
                ${open ? `<td><button class="btn btn-sm btn-link text-danger p-0 item-remove" title="Удалить"><i class="bi bi-trash"></i></button></td>` : ''}
            </tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-secondary py-4">В заказе пока нет позиций</td></tr>`;

        const infoHtml = open ? `
            <form id="infoForm" class="row g-2">
                <div class="col-sm-6"><label class="form-label">Клиент</label>
                    <input class="form-control" name="customer_name" maxlength="150" value="${esc(o.customer_name || '')}"></div>
                <div class="col-sm-6"><label class="form-label">Способ оплаты</label>
                    <select class="form-select" name="payment_method_id">${App.selectOptions(APP.paymentMethods, '—', o.payment_method_id)}</select></div>
                <div class="col-sm-3"><label class="form-label">Скидка, %</label>
                    <input type="number" class="form-control" name="discount" min="0" max="100" step="0.5" value="${Number(o.discount)}"></div>
                <div class="col-sm-9"><label class="form-label">Примечание</label>
                    <input class="form-control" name="notes" maxlength="1000" value="${esc(o.notes || '')}"></div>
                <div class="col-12 text-end"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-save me-1"></i>Сохранить</button></div>
            </form>` : `
            <dl class="row mb-0 small">
                <dt class="col-4 text-secondary fw-normal">Клиент</dt><dd class="col-8">${esc(o.customer_name || '—')}</dd>
                <dt class="col-4 text-secondary fw-normal">Способ оплаты</dt><dd class="col-8">${esc(o.payment_method || '—')}</dd>
                <dt class="col-4 text-secondary fw-normal">Скидка на заказ</dt><dd class="col-8">${Number(o.discount)}%</dd>
                <dt class="col-4 text-secondary fw-normal">Примечание</dt><dd class="col-8">${esc(o.notes || '—')}</dd>
            </dl>`;

        const addHtml = open ? `
            <div class="position-relative mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-plus-lg"></i></span>
                    <input type="search" class="form-control" id="addSearch" placeholder="Добавить деталь: артикул или название…" autocomplete="off">
                </div>
                <div class="list-group position-absolute w-100 shadow" id="addResults" style="z-index:5; max-height:260px; overflow:auto"></div>
            </div>` : '';

        let actions = `<button class="btn btn-light" id="printOrder"><i class="bi bi-printer me-1"></i>Печать</button>`;
        if (open) {
            actions += `<button class="btn btn-outline-danger" id="cancelOrder"><i class="bi bi-x-circle me-1"></i>Отменить</button>
                        <button class="btn btn-primary" id="completeOrder"><i class="bi bi-check2-circle me-1"></i>Выполнить</button>`;
        } else if (Number(o.status) === 1) {
            actions += `<button class="btn btn-outline-secondary" id="reopenOrder"><i class="bi bi-unlock me-1"></i>Открыть заново</button>
                        <button class="btn btn-outline-danger" id="cancelOrder"><i class="bi bi-x-circle me-1"></i>Отменить</button>`;
        }

        $('#orderBody').html(`
            <div class="d-flex flex-wrap gap-3 text-secondary small mb-3">
                <span><i class="bi bi-calendar3 me-1"></i>${dateTime(o.created_at)}</span>
                <span><i class="bi bi-pencil me-1"></i>изменён ${dateTime(o.updated_at)}</span>
            </div>
            <div class="card mb-3"><div class="card-body">${infoHtml}</div></div>
            ${addHtml}
            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead><tr><th>Деталь</th><th>Кол-во</th><th class="num">Цена</th><th>Скидка</th><th class="num">Сумма</th>${open ? '<th></th>' : ''}</tr></thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
            </div>
            <div class="d-flex flex-column align-items-end gap-1 mb-4">
                <span class="text-secondary">Сумма позиций: ${money(o.total_amount)}</span>
                ${Number(o.discount) ? `<span class="text-secondary">Скидка ${Number(o.discount)}%: − ${money(o.total_amount - o.final_amount)}</span>` : ''}
                <span class="fs-4 fw-bold text-accent">Итого: ${money(o.final_amount)}</span>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">${actions}</div>`);

        panel.show();
    }

    function refreshAfter(promise) {
        return promise.then(res => {
            if (res.order) showOrder(res.order);
            loadList();
        }).catch(() => current && openOrder(current.id));
    }

    // Обработчики внутри карточки
    $('#orderBody')
        .on('submit', '#infoForm', function (e) {
            e.preventDefault();
            refreshAfter(api('orders', 'update_info', Object.assign({ order_id: current.id }, App.formData($(this)))));
        })
        .on('change', '.item-qty, .item-discount', function () {
            const $tr = $(this).closest('tr');
            refreshAfter(api('orders', 'update_item', {
                order_id: current.id,
                item_id: $tr.data('item'),
                quantity: $tr.find('.item-qty').val(),
                discount: $tr.find('.item-discount').val() || 0,
            }));
        })
        .on('click', '.item-remove', function () {
            const itemId = $(this).closest('tr').data('item');
            App.confirm('Удалить позицию? Товар вернётся на склад.', 'Удалить', 'btn-danger').then(ok => {
                if (ok) refreshAfter(api('orders', 'remove_item', { order_id: current.id, item_id: itemId }));
            });
        })
        .on('input', '#addSearch', App.debounce(function () {
            const q = this.value.trim();
            if (q.length < 2) { $('#addResults').empty(); return; }
            api('catalog', 'parts', { search: q, stock: 'in' }, { silent: true }).then(res => {
                $('#addResults').html(res.parts.slice(0, 10).map(p => `
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-part="${p.id}">
                        <div class="flex-grow-1 overflow-hidden"><div class="text-truncate">${esc(p.name)}</div>
                            <small class="text-secondary">${esc(p.code)} · на складе ${p.quantity}</small></div>
                        <span class="fw-semibold">${money(p.selling_price)}</span>
                    </button>`).join('') || '<div class="list-group-item text-secondary">Ничего не найдено</div>');
            });
        }, 250))
        .on('click', '[data-part]', function () {
            $('#addResults').empty();
            refreshAfter(api('orders', 'add_item', { order_id: current.id, part_id: $(this).data('part'), quantity: 1 }));
        })
        .on('click', '#completeOrder', function () {
            refreshAfter(api('orders', 'complete', { order_id: current.id }));
        })
        .on('click', '#cancelOrder', function () {
            App.confirm('Отменить заказ № ' + current.id + '? Все товары вернутся на склад.', 'Отменить заказ', 'btn-danger').then(ok => {
                if (ok) refreshAfter(api('orders', 'cancel', { order_id: current.id }));
            });
        })
        .on('click', '#reopenOrder', function () {
            refreshAfter(api('orders', 'reopen', { order_id: current.id }));
        })
        .on('click', '#printOrder', function () {
            App.printReceipt(current);
        });

    // ---------- старт ----------
    loadList();
    if (APP.openId) openOrder(APP.openId);
});
