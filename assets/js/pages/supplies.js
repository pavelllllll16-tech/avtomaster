/**
 * Поставки: приход товара по накладной, история накладных, оплата и возвраты.
 */
$(function () {
    const { esc, money, api, date } = App;
    const DRAFT_KEY = 'supply_draft';
    const $head = $('#headForm');

    // ================= Приход =================
    let items = loadDraft();   // [{part_id, code, name, quantity, purchase_price, selling_price}]

    function loadDraft() {
        try { return JSON.parse(localStorage.getItem(DRAFT_KEY)) || []; } catch (e) { return []; }
    }
    function saveDraft() {
        try { localStorage.setItem(DRAFT_KEY, JSON.stringify(items)); } catch (e) {}
    }

    function renderItems() {
        if (!items.length) {
            $('#itemsBody').html(`<tr><td colspan="7">${App.emptyState('inbox', 'Добавьте позиции через поиск выше')}</td></tr>`);
        } else {
            $('#itemsBody').html(items.map((it, i) => {
                const buy = Number(it.purchase_price), sell = Number(it.selling_price);
                const markup = buy > 0 ? ((sell - buy) / buy * 100).toFixed(1) + '%' : '—';
                return `<tr data-i="${i}">
                    <td><div class="fw-semibold">${esc(it.name)}</div><small class="text-secondary">${esc(it.code)}</small></td>
                    <td><input type="number" class="form-control form-control-sm f" data-f="quantity" min="1" value="${it.quantity}"></td>
                    <td><input type="number" class="form-control form-control-sm f" data-f="purchase_price" min="0" step="0.01" value="${it.purchase_price}"></td>
                    <td><input type="number" class="form-control form-control-sm f" data-f="selling_price" min="0" step="0.01" value="${it.selling_price}"></td>
                    <td class="num ${sell < buy ? 'text-danger' : ''}">${markup}</td>
                    <td class="num fw-semibold">${money(it.quantity * buy)}</td>
                    <td><button class="btn btn-sm btn-link text-danger p-0 remove"><i class="bi bi-x-lg"></i></button></td>
                </tr>`;
            }).join(''));
        }
        $('#itemsTotal').text(money(items.reduce((s, it) => s + it.quantity * it.purchase_price, 0)));
        $('#submitSupply').prop('disabled', !items.length);
    }

    $('#itemsBody')
        .on('change', '.f', function () {
            const i = $(this).closest('tr').data('i');
            const f = $(this).data('f');
            items[i][f] = f === 'quantity' ? Math.max(1, parseInt(this.value, 10) || 1) : Math.max(0, Number(this.value) || 0);
            saveDraft();
            renderItems();
        })
        .on('click', '.remove', function () {
            items.splice($(this).closest('tr').data('i'), 1);
            saveDraft();
            renderItems();
        });

    $('#partSearch').on('input', App.debounce(function () {
        const q = this.value.trim();
        if (q.length < 2) { $('#partResults').empty(); return; }
        api('catalog', 'parts', { search: q }, { silent: true }).then(res => {
            $('#partResults').html(res.parts.slice(0, 12).map(p => `
                <button type="button" class="list-group-item list-group-item-action d-flex gap-2 align-items-center" data-p='${esc(JSON.stringify(p))}'>
                    <div class="flex-grow-1 overflow-hidden"><div class="text-truncate">${esc(p.name)}</div>
                        <small class="text-secondary">${esc(p.code)} · остаток ${p.quantity} · закупка ${money(p.purchase_price)}</small></div>
                    <i class="bi bi-plus-circle text-accent"></i>
                </button>`).join('') || '<div class="list-group-item text-secondary">Не найдено. Новую позицию заведите в каталоге.</div>');
        });
    }, 250));

    $('#partResults').on('click', '[data-p]', function () {
        const p = $(this).data('p');
        const existing = items.find(it => it.part_id === p.id);
        if (existing) {
            existing.quantity++;
        } else {
            items.push({ part_id: p.id, code: p.code, name: p.name, quantity: 1,
                         purchase_price: Number(p.purchase_price), selling_price: Number(p.selling_price) });
        }
        $('#partResults').empty();
        $('#partSearch').val('').trigger('focus');
        saveDraft();
        renderItems();
    });

    $('#clearItems').on('click', function () {
        if (!items.length) return;
        App.confirm('Очистить состав накладной?', 'Очистить', 'btn-danger').then(ok => {
            if (ok) { items = []; saveDraft(); renderItems(); }
        });
    });

    $('#submitSupply').on('click', function () {
        if (!$head[0].reportValidity()) return;
        const total = items.reduce((s, it) => s + it.quantity * it.purchase_price, 0);
        App.confirm(`Провести накладную на сумму ${money(total)}? Остатки и цены в каталоге будут обновлены.`, 'Провести').then(ok => {
            if (!ok) return;
            const $btn = $('#submitSupply').prop('disabled', true);
            api('supplies', 'create', Object.assign(App.formData($head), { items: JSON.stringify(items) })).then(() => {
                items = [];
                saveDraft();
                renderItems();
                $head.find('[name=invoice_number]').val('');
                return api('supplies', 'next_number', {}, { silent: true });
            }).then(res => {
                $head.find('[name=invoice_number]').attr('placeholder', res.number);
                loadInvoices();
            }).finally(() => $btn.prop('disabled', !items.length));
        });
    });

    // ================= История =================
    const invoiceModal = new bootstrap.Modal('#invoiceModal');
    const returnModal = new bootstrap.Modal('#returnModal');
    let invoices = [];
    let currentInvoice = null;
    let currentItems = [];

    function renderStats(s) {
        const cards = [
            ['Накладных', App.num(s.invoices), 'journal-text', ''],
            ['Закуплено на сумму', money(s.total_amount), 'cart-check', 'blue'],
            ['Не оплачено', money(s.unpaid_amount), 'hourglass-split', ''],
            ['Просрочено', money(s.overdue_amount), 'alarm', Number(s.overdue_amount) > 0 ? 'red' : 'green'],
        ];
        $('#statsRow').html(cards.map(c => `
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="kpi">
                <div class="kpi-icon ${c[3]}"><i class="bi bi-${c[2]}"></i></div>
                <div><div class="kpi-label">${c[0]}</div><div class="kpi-value">${c[1]}</div></div>
            </div></div></div>`).join(''));
    }

    function loadInvoices() {
        $('#invoicesBody').addClass('loading');
        return api('supplies', 'invoices', App.formData($('#historyFilters')), { silent: true }).then(res => {
            invoices = res.invoices;
            renderStats(res.stats);
            const today = App.today();
            if (!invoices.length) {
                $('#invoicesBody').html(`<tr><td colspan="10">${App.emptyState('journal', 'Накладные не найдены')}</td></tr>`);
                return;
            }
            $('#invoicesBody').html(invoices.map((inv, i) => {
                const overdue = inv.payment_status === 'unpaid' && inv.due_date < today;
                const status = inv.payment_status === 'paid'
                    ? '<span class="badge-soft success">Оплачена</span>'
                    : (overdue ? '<span class="badge-soft danger">Просрочена</span>' : '<span class="badge-soft warning">Не оплачена</span>');
                return `<tr data-i="${i}" role="button">
                    <td class="fw-semibold text-nowrap">${esc(inv.invoice_number)}</td>
                    <td>${date(inv.supply_date)}</td>
                    <td>${esc(inv.supplier_name)}</td>
                    <td class="text-center">${inv.positions}</td>
                    <td class="num">${money(inv.total_amount)}</td>
                    <td class="num text-secondary">${Number(inv.returned_amount) ? '− ' + money(inv.returned_amount) : '—'}</td>
                    <td class="num fw-semibold">${money(inv.amount_due)}</td>
                    <td class="${overdue ? 'text-danger fw-semibold' : ''}">${date(inv.due_date)}</td>
                    <td>${status}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm ${inv.payment_status === 'paid' ? 'btn-light' : 'btn-outline-success'} toggle-paid">
                            ${inv.payment_status === 'paid' ? 'Снять оплату' : 'Оплачено'}</button>
                    </td>
                </tr>`;
            }).join(''));
        }).finally(() => $('#invoicesBody').removeClass('loading'));
    }

    $('#historyFilters').on('input change', App.debounce(loadInvoices, 300)).on('submit', e => e.preventDefault());

    $('#invoicesBody')
        .on('click', '.toggle-paid', function (e) {
            e.stopPropagation();
            const inv = invoices[$(this).closest('tr').data('i')];
            const paid = inv.payment_status !== 'paid';
            App.confirm(`${paid ? 'Отметить оплату' : 'Снять отметку об оплате'} накладной № ${inv.invoice_number}?`).then(ok => {
                if (ok) api('supplies', 'set_paid', { invoice: inv.invoice_number, paid: paid ? 1 : 0 }).then(loadInvoices);
            });
        })
        .on('click', 'tr[data-i]', function () {
            openInvoice(invoices[$(this).data('i')]);
        });

    function openInvoice(inv) {
        currentInvoice = inv;
        api('supplies', 'invoice', { invoice: inv.invoice_number }, { silent: true }).then(res => {
            currentItems = res.items;
            $('#invoiceTitle').text(`Накладная № ${inv.invoice_number} от ${date(inv.supply_date)} — ${inv.supplier_name}`);
            const rows = res.items.map((it, i) => `
                <tr data-i="${i}">
                    <td><div class="fw-semibold">${esc(it.name)}</div><small class="text-secondary">${esc(it.code)}</small></td>
                    <td class="text-center">${it.quantity}</td>
                    <td class="num">${money(it.price)}</td>
                    <td class="num">${money(it.quantity * it.price)}</td>
                    <td class="text-center">${Number(it.returned) ? `<span class="badge-soft danger">${it.returned}</span>` : '—'}</td>
                    <td class="text-center">${it.stock}</td>
                    <td class="text-end">${Number(it.remaining) > 0
                        ? '<button class="btn btn-sm btn-outline-danger do-return"><i class="bi bi-arrow-return-left me-1"></i>Возврат</button>' : ''}</td>
                </tr>`).join('');
            const returns = res.returns.length ? `
                <h6 class="mt-4">История возвратов</h6>
                <table class="table table-sm"><thead><tr><th>Дата</th><th>Запчасть</th><th class="text-center">Кол-во</th><th class="num">Сумма</th><th>Причина</th></tr></thead>
                <tbody>${res.returns.map(r => `<tr><td>${date(r.return_date)}</td><td>${esc(r.name)}</td><td class="text-center">${r.quantity}</td>
                    <td class="num">${money(r.quantity * r.price)}</td><td>${esc(r.reason || '—')}</td></tr>`).join('')}</tbody></table>` : '';
            $('#invoiceBody').html(`
                <div class="d-flex flex-wrap gap-4 mb-3 small text-secondary">
                    <span>Оплата: <b class="text-body">${esc(inv.payment_method || '—')}</b></span>
                    <span>Отсрочка: <b class="text-body">${inv.payment_delay_days} дн. (до ${date(inv.due_date)})</b></span>
                    <span>К оплате: <b class="text-body">${money(inv.amount_due)}</b></span>
                </div>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Запчасть</th><th class="text-center">Кол-во</th><th class="num">Цена</th><th class="num">Сумма</th>
                        <th class="text-center">Возвращено</th><th class="text-center">На складе</th><th></th></tr></thead>
                    <tbody>${rows}</tbody></table></div>${returns}`);
            invoiceModal.show();
        });
    }

    $('#invoiceBody').on('click', '.do-return', function () {
        const it = currentItems[$(this).closest('tr').data('i')];
        const max = Math.min(Number(it.remaining), Number(it.stock));
        App.fillForm($('#returnForm'), { supply_id: it.id, quantity: 1, return_date: App.today(), reason: '' });
        $('#returnForm [name=quantity]').attr('max', max);
        $('#returnPart').html(`<b>${esc(it.name)}</b><br><small class="text-secondary">Можно вернуть до ${max} шт. (осталось по накладной ${it.remaining}, на складе ${it.stock})</small>`);
        returnModal.show();
    });

    $('#returnForm').on('submit', function (e) {
        e.preventDefault();
        api('supplies', 'return_item', App.formData($(this))).then(() => {
            returnModal.hide();
            loadInvoices().then(() => {
                const inv = invoices.find(x => x.invoice_number === currentInvoice.invoice_number);
                if (inv) openInvoice(inv);
            });
        });
    });

    // ================= старт =================
    $head.find('[name=supplier_id]').html(App.selectOptions(APP.suppliers, 'Выберите поставщика…'));
    $head.find('[name=payment_method_id]').html(App.selectOptions(APP.paymentMethods, '—'));
    $head.find('[name=supply_date]').val(App.today());
    $head.find('[name=invoice_number]').attr('placeholder', APP.nextNumber);
    $('#historyFilters [name=supplier_id]').html(App.selectOptions(APP.suppliers, 'Все'));
    $('#historyFilters [name=payment_status]').val(APP.status);
    renderItems();
    loadInvoices();

    bootstrap.Tab.getOrCreateInstance(APP.tab === 'history' ? '#tabHistoryBtn' : '#tabReceiveBtn').show();
});
