/**
 * Общие функции интерфейса: запросы к API, уведомления, форматирование.
 */
const App = (function () {
    'use strict';

    /** Экранирование текста перед вставкой в HTML (защита от XSS) */
    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    const moneyFmt = new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const intFmt = new Intl.NumberFormat('ru-RU');

    function money(value) {
        return moneyFmt.format(Number(value) || 0) + ' ' + APP.currency;
    }

    function num(value) {
        return intFmt.format(Number(value) || 0);
    }

    /** '2026-10-28 14:05:00' → '28.10.2026 14:05' */
    function dateTime(value) {
        if (!value) return '';
        const [d, t] = String(value).split(' ');
        const [y, m, day] = d.split('-');
        return `${day}.${m}.${y}` + (t ? ' ' + t.slice(0, 5) : '');
    }

    function date(value) {
        return dateTime(String(value || '').split(' ')[0]);
    }

    function today() {
        const d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 10);
    }

    /**
     * Запрос к API. Возвращает Promise с ответом сервера.
     * При ошибке показывает уведомление и отклоняет Promise.
     */
    function api(module, action, data, options) {
        options = options || {};
        const payload = Object.assign({ action: action }, data || {});
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: 'api/' + module + '.php',
                method: 'POST',
                data: payload,
                dataType: 'json',
                headers: { 'X-CSRF-Token': APP.csrf },
            }).done(function (res) {
                if (res && res.ok) {
                    if (res.message && !options.silent) toast(res.message, 'success');
                    resolve(res);
                } else {
                    toast((res && res.message) || 'Ошибка', 'danger');
                    reject(res);
                }
            }).fail(function (xhr) {
                const res = xhr.responseJSON;
                toast((res && res.message) || 'Сервер недоступен', 'danger');
                reject(res);
            });
        });
    }

    function toast(message, type) {
        const icons = { success: 'check-circle-fill', danger: 'exclamation-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
        type = type || 'info';
        const $t = $(`
            <div class="toast align-items-center border-0 text-bg-${type}" role="alert">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-${icons[type] || icons.info} me-2"></i>${esc(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);
        $('#toasts').append($t);
        const t = new bootstrap.Toast($t[0], { delay: type === 'danger' ? 6000 : 3000 });
        $t.on('hidden.bs.toast', function () { $t.remove(); });
        t.show();
    }

    /** Подтверждение действия в модальном окне. Возвращает Promise<boolean> */
    function confirm(text, okText, okClass) {
        return new Promise(function (resolve) {
            const el = document.getElementById('confirmModal');
            const modal = bootstrap.Modal.getOrCreateInstance(el);
            let result = false;
            $('#confirmText').text(text);
            $('#confirmOk').text(okText || 'Подтвердить').attr('class', 'btn ' + (okClass || 'btn-primary'))
                .off('click').on('click', function () { result = true; modal.hide(); });
            $(el).off('hidden.bs.modal').on('hidden.bs.modal', function () { resolve(result); });
            modal.show();
        });
    }

    function debounce(fn, ms) {
        let timer;
        return function () {
            const args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, ms || 300);
        };
    }

    /** Данные формы в виде объекта */
    function formData($form) {
        const obj = {};
        $form.serializeArray().forEach(function (f) { obj[f.name] = f.value; });
        $form.find('input[type=checkbox]').each(function () {
            if (this.name) obj[this.name] = this.checked ? 1 : 0;
        });
        return obj;
    }

    /** Заполнить форму значениями из объекта */
    function fillForm($form, data) {
        $form[0].reset();
        $form.find('input[type=hidden]').val('');   // reset() не очищает скрытые поля
        Object.keys(data).forEach(function (k) {
            const $f = $form.find('[name="' + k + '"]');
            if ($f.is(':checkbox')) $f.prop('checked', !!Number(data[k]));
            else $f.val(data[k] == null ? '' : data[k]);
        });
    }

    function emptyState(icon, text) {
        return `<div class="empty-state"><i class="bi bi-${icon}"></i>${esc(text)}</div>`;
    }

    function stockBadge(p) {
        const map = { ok: ['success', 'В наличии'], low: ['warning', 'Заканчивается'], out: ['danger', 'Нет'] };
        const s = map[p.stock_state] || map.ok;
        return `<span class="badge-soft ${s[0]}"><span class="stock-dot ${p.stock_state}"></span>${num(p.quantity)} шт.</span>`;
    }

    const ORDER_STATUS = {
        0: ['warning', 'Открыт'],
        1: ['success', 'Выполнен'],
        2: ['muted', 'Отменён'],
    };

    function orderStatusBadge(status) {
        const s = ORDER_STATUS[status] || ORDER_STATUS[0];
        return `<span class="badge-soft ${s[0]}">${s[1]}</span>`;
    }

    function selectOptions(list, placeholder, selected) {
        let html = placeholder != null ? `<option value="">${esc(placeholder)}</option>` : '';
        list.forEach(function (i) {
            html += `<option value="${i.id}" ${String(i.id) === String(selected) ? 'selected' : ''}>${esc(i.name)}</option>`;
        });
        return html;
    }

    /** Печать товарного чека в отдельном окне */
    function printReceipt(order) {
        const rows = order.items.map((it, i) => `
            <tr><td>${i + 1}</td><td>${esc(it.name)}<br><small>${esc(it.code)}</small></td>
                <td class="r">${it.quantity}</td><td class="r">${money(it.price)}</td>
                <td class="r">${Number(it.discount) ? it.discount + '%' : ''}</td><td class="r">${money(it.total_price)}</td></tr>`).join('');
        const html = `<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Чек № ${order.id}</title>
            <style>
                body{font-family:Arial,sans-serif;font-size:12px;max-width:560px;margin:20px auto;color:#000}
                h2{margin:0 0 4px;font-size:16px} table{width:100%;border-collapse:collapse;margin:12px 0}
                th,td{border-bottom:1px solid #999;padding:4px;text-align:left;vertical-align:top} .r{text-align:right;white-space:nowrap}
                .tot{font-size:15px;font-weight:bold;text-align:right} .muted{color:#555}
            </style></head><body>
            <h2>${esc(APP.shopName)}</h2>
            <div>Товарный чек № ${order.id} от ${dateTime(order.created_at)}</div>
            ${order.customer_name ? `<div>Покупатель: ${esc(order.customer_name)}</div>` : ''}
            <table><thead><tr><th>№</th><th>Товар</th><th class="r">Кол-во</th><th class="r">Цена</th><th class="r">Скидка</th><th class="r">Сумма</th></tr></thead>
            <tbody>${rows}</tbody></table>
            <div class="r">Сумма: ${money(order.total_amount)}</div>
            ${Number(order.discount) ? `<div class="r">Скидка на заказ: ${order.discount}%</div>` : ''}
            <div class="tot">Итого: ${money(order.final_amount)}</div>
            <div class="muted">Оплата: ${esc(order.payment_method || '—')}</div>
            <p style="margin-top:30px">Подпись продавца ________________</p>
            <script>window.onload=function(){window.print()}<\/script></body></html>`;
        const w = window.open('', '_blank', 'width=640,height=720');
        if (!w) { toast('Разрешите всплывающие окна для печати', 'warning'); return; }
        w.document.write(html);
        w.document.close();
    }

    // ---------- Общие обработчики макета ----------
    $(function () {
        // Мобильное меню
        $('#sidebarToggle').on('click', function () { $('#sidebar').toggleClass('open'); });
        $('#sidebarBackdrop').on('click', function () { $('#sidebar').removeClass('open'); });

        // Переключение темы
        function applyThemeIcon() {
            const dark = document.documentElement.dataset.bsTheme === 'dark';
            $('#themeToggle i').attr('class', dark ? 'bi bi-sun' : 'bi bi-moon-stars');
        }
        applyThemeIcon();
        $('#themeToggle').on('click', function () {
            const next = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.bsTheme = next;
            try { localStorage.setItem('theme', next); } catch (e) {}
            applyThemeIcon();
            $(document).trigger('themechange');
        });

        // Часы в верхней панели
        function tick() {
            $('#clock').text(new Date().toLocaleString('ru-RU', { weekday: 'short', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }));
        }
        tick();
        setInterval(tick, 30000);

        // Подсказки
        $('[data-bs-toggle="tooltip"]').each(function () { new bootstrap.Tooltip(this); });
    });

    return {
        esc, money, num, date, dateTime, today, api, toast, confirm, debounce,
        formData, fillForm, emptyState, stockBadge, orderStatusBadge, selectOptions, printReceipt,
    };
})();
