/**
 * Отчёты: продажи, прибыль, склад, ценники.
 */
$(function () {
    const { esc, money, num, api, date, dateTime } = App;
    let report = 'sales';
    let chart = null;

    // ---------- период ----------
    function iso(d) {
        d = new Date(d);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 10);
    }
    function setPeriod(p) {
        const now = new Date();
        let from = new Date(now), to = new Date(now);
        if (p === 'week') from.setDate(now.getDate() - 6);
        if (p === 'month') from = new Date(now.getFullYear(), now.getMonth(), 1);
        if (p === 'prev') { from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); }
        if (p === 'year') from = new Date(now.getFullYear(), 0, 1);
        $('#dateFrom').val(iso(from));
        $('#dateTo').val(iso(to));
        $('.period').removeClass('active').filter(`[data-p="${p}"]`).addClass('active');
    }
    const period = () => ({ date_from: $('#dateFrom').val(), date_to: $('#dateTo').val() });

    $('.period').on('click', function () { setPeriod($(this).data('p')); load(); });
    $('#dateFrom, #dateTo').on('change', function () { $('.period').removeClass('active'); load(); });

    $('#reportTabs [data-report]').on('shown.bs.tab', function () {
        report = $(this).data('report');
        load();
    });

    function load() {
        const p = period();
        $('#printTitle').text($('#reportTabs .active').text().trim() + (report === 'stock' || report === 'tags'
            ? ' на ' + date(App.today()) : ` за период ${date(p.date_from)} — ${date(p.date_to)}`));
        if (report === 'sales') loadSales(p);
        if (report === 'profit') loadProfit(p);
        if (report === 'stock') loadStock();
        if (report === 'tags') loadTags();
    }

    const pct = (a, b) => Number(b) ? (Number(a) / Number(b) * 100).toFixed(1) + '%' : '—';
    const empty = (cols, text) => `<tr><td colspan="${cols}">${App.emptyState('inbox', text || 'Нет данных за выбранный период')}</td></tr>`;

    // ---------- продажи ----------
    function loadSales(p) {
        api('reports', 'sales', p, { silent: true }).then(res => {
            const rows = res.rows;
            const revenue = rows.reduce((s, r) => s + Number(r.final_amount), 0);
            const profit = rows.reduce((s, r) => s + Number(r.profit), 0);
            const kpi = [
                ['Заказов', num(rows.length), 'receipt', ''],
                ['Выручка', money(revenue), 'graph-up-arrow', 'green'],
                ['Прибыль', money(profit), 'piggy-bank', 'blue'],
                ['Средний чек', money(rows.length ? revenue / rows.length : 0), 'receipt-cutoff', ''],
            ];
            $('#salesKpi').html(kpi.map(k => `<div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="kpi">
                <div class="kpi-icon ${k[3]}"><i class="bi bi-${k[2]}"></i></div>
                <div><div class="kpi-label">${k[0]}</div><div class="kpi-value">${k[1]}</div></div></div></div></div>`).join(''));

            $('#salesBody').html(rows.length ? rows.map(r => `<tr>
                <td class="fw-semibold">${r.id}</td>
                <td class="text-nowrap">${dateTime(r.created_at)}</td>
                <td>${esc(r.customer_name || '—')}</td>
                <td class="small">${r.items.map(i => `${esc(i.code)} — ${esc(i.name)} (${i.quantity} × ${money(i.price)})`).join('<br>')}</td>
                <td>${esc(r.payment_method || '—')}</td>
                <td class="num fw-semibold">${money(r.final_amount)}</td>
                <td class="num">${money(r.profit)}</td></tr>`).join('') : empty(7));
            $('#salesFoot').html(rows.length ? `<tr><td colspan="5">Итого: ${rows.length} заказ(ов)</td>
                <td class="num">${money(revenue)}</td><td class="num">${money(profit)}</td></tr>` : '');
        });
    }

    // ---------- прибыль ----------
    function loadProfit(p) {
        api('reports', 'profit', p, { silent: true }).then(res => {
            const rows = res.rows;
            const t = rows.reduce((a, r) => ({ sold: a.sold + Number(r.sold), revenue: a.revenue + Number(r.revenue),
                cost: a.cost + Number(r.cost), profit: a.profit + Number(r.profit) }), { sold: 0, revenue: 0, cost: 0, profit: 0 });
            $('#profitBody').html(rows.length ? rows.map(r => `<tr>
                <td>${esc(r.category_name)}</td><td>${esc(r.subcategory_name)}</td>
                <td class="num">${num(r.sold)}</td><td class="num">${money(r.revenue)}</td><td class="num">${money(r.cost)}</td>
                <td class="num fw-semibold ${Number(r.profit) < 0 ? 'text-danger' : ''}">${money(r.profit)}</td>
                <td class="num">${pct(r.profit, r.revenue)}</td></tr>`).join('') : empty(7));
            $('#profitFoot').html(rows.length ? `<tr><td colspan="2">Итого</td><td class="num">${num(t.sold)}</td>
                <td class="num">${money(t.revenue)}</td><td class="num">${money(t.cost)}</td><td class="num">${money(t.profit)}</td>
                <td class="num">${pct(t.profit, t.revenue)}</td></tr>` : '');

            $('#topBody').html(res.top.length ? res.top.map((r, i) => `<tr><td>${i + 1}</td><td class="fw-semibold">${esc(r.code)}</td>
                <td>${esc(r.name)}</td><td class="num">${num(r.sold)}</td><td class="num">${money(r.revenue)}</td></tr>`).join('') : empty(5));

            if (chart) chart.destroy();
            chart = null;
            if (!res.categories.length) {
                $('#profitChart').hide();
                return;
            }
            $('#profitChart').show();
            chart = new Chart(document.getElementById('profitChart'), {
                type: 'doughnut',
                data: {
                    labels: res.categories.map(c => c.name),
                    datasets: [{ data: res.categories.map(c => Number(c.revenue)), borderWidth: 0,
                        backgroundColor: ['#f26b1d', '#0d6efd', '#198754', '#ffb020', '#6f42c1', '#20c997', '#dc3545'] }],
                },
                options: { cutout: '65%', plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: getComputedStyle(document.body).color } },
                    tooltip: { callbacks: { label: c => c.label + ': ' + money(c.parsed) } } } },
            });
        });
    }

    // ---------- склад ----------
    function loadStock() {
        api('reports', 'stock', {}, { silent: true }).then(res => {
            const rows = res.rows;
            const t = rows.reduce((a, r) => ({ positions: a.positions + Number(r.positions), quantity: a.quantity + Number(r.quantity),
                buy: a.buy + Number(r.purchase_value), sell: a.sell + Number(r.selling_value), profit: a.profit + Number(r.potential_profit) }),
                { positions: 0, quantity: 0, buy: 0, sell: 0, profit: 0 });
            $('#stockBody').html(rows.length ? rows.map(r => `<tr>
                <td>${esc(r.category_name)}</td><td>${esc(r.subcategory_name)}</td>
                <td class="num">${num(r.positions)}</td><td class="num">${num(r.quantity)}</td>
                <td class="num">${money(r.purchase_value)}</td><td class="num">${money(r.selling_value)}</td>
                <td class="num fw-semibold">${money(r.potential_profit)}</td>
                <td class="num">${r.avg_markup == null ? '—' : r.avg_markup + '%'}</td></tr>`).join('') : empty(8, 'Каталог пуст'));
            $('#stockFoot').html(rows.length ? `<tr><td colspan="2">Итого</td><td class="num">${num(t.positions)}</td><td class="num">${num(t.quantity)}</td>
                <td class="num">${money(t.buy)}</td><td class="num">${money(t.sell)}</td><td class="num">${money(t.profit)}</td>
                <td class="num">${pct(t.sell - t.buy, t.buy)}</td></tr>` : '');
        });
    }

    // ---------- ценники ----------
    let tagParts = [];
    const selected = new Map();   // id → запчасть

    function loadTags() {
        api('catalog', 'parts', { category_id: $('#tagCategory').val(), search: $('#tagSearch').val().trim() }, { silent: true }).then(res => {
            tagParts = res.parts;
            $('#tagsBody').html(tagParts.length ? tagParts.map((p, i) => `<tr data-i="${i}">
                <td><input type="checkbox" class="form-check-input tag-check" ${selected.has(p.id) ? 'checked' : ''}></td>
                <td class="fw-semibold">${esc(p.code)}</td><td>${esc(p.name)}</td>
                <td class="small text-secondary">${esc(p.category_name)}</td>
                <td class="num">${money(p.selling_price)}</td><td class="text-center">${p.quantity}</td></tr>`).join('') : empty(6, 'Ничего не найдено'));
            $('#tagAll').prop('checked', tagParts.length && tagParts.every(p => selected.has(p.id)));
        });
    }

    function updateTagCount() {
        $('#tagCount').text(selected.size);
        $('#printTags').prop('disabled', !selected.size);
    }

    $('#tagCategory').html(App.selectOptions(APP.categories, 'Все категории')).on('change', loadTags);
    $('#tagSearch').on('input', App.debounce(loadTags, 300));
    $('#tagsBody').on('change', '.tag-check', function () {
        const p = tagParts[$(this).closest('tr').data('i')];
        this.checked ? selected.set(p.id, p) : selected.delete(p.id);
        updateTagCount();
    }).on('click', 'tr', function (e) {
        if (!$(e.target).is('input')) $(this).find('.tag-check').trigger('click');
    });
    $('#tagAll').on('change', function () {
        tagParts.forEach(p => this.checked ? selected.set(p.id, p) : selected.delete(p.id));
        $('.tag-check').prop('checked', this.checked);
        updateTagCount();
    });

    $('#printTags').on('click', function () {
        const today = date(App.today());
        const tags = [...selected.values()].map(p => `
            <div class="tag"><div class="shop">${esc(APP.shopName)}</div>
                <div class="name">${esc(p.name)}</div><div class="code">Арт. ${esc(p.code)}</div>
                <div class="price">${money(p.selling_price)}</div><div class="date">${today}</div></div>`).join('');
        const w = window.open('', '_blank', 'width=900,height=700');
        if (!w) { App.toast('Разрешите всплывающие окна для печати', 'warning'); return; }
        w.document.write(`<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Ценники</title><style>
            @page{size:A4;margin:8mm} body{font-family:Arial,sans-serif;margin:0}
            .wrap{display:flex;flex-wrap:wrap;gap:3mm}
            .tag{width:62mm;height:38mm;box-sizing:border-box;border:1px solid #000;border-radius:2mm;padding:2.5mm 3mm;display:flex;flex-direction:column;page-break-inside:avoid}
            .shop{font-size:7pt;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #000;padding-bottom:1mm}
            .name{font-size:9.5pt;font-weight:bold;line-height:1.15;margin-top:1.5mm;max-height:3.5em;overflow:hidden}
            .code{font-size:7.5pt;color:#333}
            .price{margin-top:auto;font-size:19pt;font-weight:800;text-align:right}
            .date{font-size:6.5pt;color:#555}
        </style></head><body><div class="wrap">${tags}</div><script>window.onload=function(){window.print()}<\/script></body></html>`);
        w.document.close();
    });

    // ---------- выгрузка CSV ----------
    $('#exportCsv').on('click', function () {
        if (report === 'tags') { App.toast('Для ценников выгрузка не предусмотрена', 'info'); return; }
        const p = period();
        location.href = `export.php?type=${report}&date_from=${p.date_from}&date_to=${p.date_to}`;
    });

    // ---------- старт ----------
    setPeriod('month');
    load();
});
