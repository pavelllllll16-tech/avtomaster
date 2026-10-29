/**
 * Касса: подбор товара, корзина, оформление продажи.
 * Корзина хранится в браузере (переживает обновление страницы),
 * окончательная проверка цен и остатков выполняется на сервере.
 */
$(function () {
    const { esc, money, api, toast } = App;
    const CART_KEY = 'pos_cart';

    let products = [];             // загруженные товары
    let filter = { category_id: '', subcategory_id: '', search: '' };
    let cart = loadCart();         // { partId: {id, code, name, price, stock, quantity, discount} }
    let lastOrder = null;

    // ---------- корзина ----------
    function loadCart() {
        try { return JSON.parse(localStorage.getItem(CART_KEY)) || {}; } catch (e) { return {}; }
    }
    function saveCart() {
        try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) {}
    }

    function addToCart(p) {
        const line = cart[p.id];
        const inCart = line ? line.quantity : 0;
        if (inCart + 1 > p.quantity) {
            toast('На складе только ' + p.quantity + ' шт.', 'warning');
            return;
        }
        if (line) {
            line.quantity++;
            line.stock = Number(p.quantity);
        } else {
            cart[p.id] = { id: p.id, code: p.code, name: p.name, price: Number(p.selling_price), stock: Number(p.quantity), quantity: 1, discount: 0 };
        }
        saveCart();
        renderCart();
        const $line = $(`.cart-line[data-id="${p.id}"]`);
        $line.addClass('bg-accent-soft');
        setTimeout(() => $line.removeClass('bg-accent-soft'), 400);
    }

    function lineTotal(l) {
        return l.price * l.quantity * (1 - l.discount / 100);
    }

    function renderCart() {
        const lines = Object.values(cart);
        $('#cartCount').text(lines.reduce((s, l) => s + l.quantity, 0));

        if (!lines.length) {
            $('#cartLines').html(App.emptyState('cart', 'Корзина пуста — выберите товар слева'));
        } else {
            $('#cartLines').html(lines.map(l => `
                <div class="cart-line" data-id="${l.id}">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-truncate" title="${esc(l.name)}">${esc(l.name)}</div>
                            <small class="text-secondary">${esc(l.code)} · ${money(l.price)}</small>
                        </div>
                        <button class="btn btn-sm btn-link text-danger p-0 remove-line" title="Убрать"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <div class="input-group input-group-sm" style="width:auto">
                            <button class="btn btn-outline-secondary qty-step" data-step="-1">−</button>
                            <input type="number" class="form-control text-center qty-input line-qty" min="1" max="${l.stock}" value="${l.quantity}">
                            <button class="btn btn-outline-secondary qty-step" data-step="1">+</button>
                        </div>
                        <div class="input-group input-group-sm" style="width:auto" title="Скидка на позицию">
                            <input type="number" class="form-control disc-input line-discount" min="0" max="100" value="${l.discount}">
                            <span class="input-group-text">%</span>
                        </div>
                        <span class="ms-auto fw-bold num">${money(lineTotal(l))}</span>
                    </div>
                </div>`).join(''));
        }
        renderTotals();
    }

    function renderTotals() {
        const subtotal = Object.values(cart).reduce((s, l) => s + lineTotal(l), 0);
        const disc = Math.min(100, Math.max(0, Number($('#orderDiscount').val()) || 0));
        const discountAmount = subtotal * disc / 100;
        $('#subtotal').text(money(subtotal));
        $('#discountAmount').text('− ' + money(discountAmount));
        $('#total').text(money(subtotal - discountAmount));
        $('#checkout').prop('disabled', !Object.keys(cart).length);
    }

    function setLineQty(id, qty) {
        const l = cart[id];
        if (!l) return;
        qty = Math.floor(Number(qty) || 1);
        if (qty < 1) qty = 1;
        if (qty > l.stock) {
            qty = l.stock;
            toast('На складе только ' + l.stock + ' шт.', 'warning');
        }
        l.quantity = qty;
        saveCart();
        renderCart();
    }

    $('#cartLines')
        .on('click', '.qty-step', function () {
            const id = $(this).closest('.cart-line').data('id');
            setLineQty(id, cart[id].quantity + Number($(this).data('step')));
        })
        .on('change', '.line-qty', function () {
            setLineQty($(this).closest('.cart-line').data('id'), this.value);
        })
        .on('change', '.line-discount', function () {
            const id = $(this).closest('.cart-line').data('id');
            cart[id].discount = Math.min(100, Math.max(0, Number(this.value) || 0));
            saveCart();
            renderCart();
        })
        .on('click', '.remove-line', function () {
            delete cart[$(this).closest('.cart-line').data('id')];
            saveCart();
            renderCart();
        });

    $('#orderDiscount').on('input', renderTotals);

    $('#clearCart').on('click', function () {
        if (!Object.keys(cart).length) return;
        App.confirm('Очистить корзину?', 'Очистить', 'btn-danger').then(ok => {
            if (!ok) return;
            cart = {};
            saveCart();
            renderCart();
        });
    });

    // ---------- оформление ----------
    $('#checkout').on('click', function () {
        const $btn = $(this);
        if (!$('#paymentMethod').val()) {
            toast('Выберите способ оплаты', 'warning');
            $('#paymentMethod').focus();
            return;
        }
        const items = Object.values(cart).map(l => ({ part_id: l.id, quantity: l.quantity, discount: l.discount }));
        $btn.prop('disabled', true);
        api('orders', 'checkout', {
            items: JSON.stringify(items),
            customer_name: $('#customerName').val(),
            payment_method_id: $('#paymentMethod').val(),
            discount: $('#orderDiscount').val() || 0,
            notes: $('#notes').val(),
        }, { silent: true }).then(res => {
            cart = {};
            saveCart();
            $('#customerName, #notes').val('');
            $('#orderDiscount').val(0);
            renderCart();
            loadProducts();
            return api('orders', 'get', { id: res.order_id }, { silent: true });
        }).then(res => {
            lastOrder = res.order;
            $('#doneTitle').text('Заказ № ' + lastOrder.id + ' оформлен');
            $('#doneSum').text('К оплате: ' + money(lastOrder.final_amount) + ' · ' + (lastOrder.payment_method || ''));
            bootstrap.Modal.getOrCreateInstance('#doneModal').show();
        }).catch(() => {
            loadProducts();   // обновим остатки, если товар закончился
        }).finally(() => $btn.prop('disabled', !Object.keys(cart).length));
    });

    $('#printReceipt').on('click', function () {
        if (lastOrder) App.printReceipt(lastOrder);
    });

    // ---------- подбор товара ----------
    function renderChips() {
        const cats = APP.categories;
        $('#categoryChips').html(
            `<span class="chip ${filter.category_id === '' ? 'active' : ''}" data-cat="">Все</span>` +
            cats.map(c => `<span class="chip ${String(c.id) === filter.category_id ? 'active' : ''}" data-cat="${c.id}">${esc(c.name)}</span>`).join('')
        );
        const cat = cats.find(c => String(c.id) === filter.category_id);
        $('#subcategoryChips').html(cat ? cat.subcategories.map(s =>
            `<span class="chip ${String(s.id) === filter.subcategory_id ? 'active' : ''}" data-sub="${s.id}">${esc(s.name)}</span>`).join('') : '');
    }

    $('#categoryChips').on('click', '.chip', function () {
        filter.category_id = String($(this).data('cat'));
        filter.subcategory_id = '';
        renderChips();
        loadProducts();
    });
    $('#subcategoryChips').on('click', '.chip', function () {
        const id = String($(this).data('sub'));
        filter.subcategory_id = filter.subcategory_id === id ? '' : id;
        renderChips();
        loadProducts();
    });
    $('#search').on('input', App.debounce(function () {
        filter.search = this.value.trim();
        loadProducts();
    }, 250)).on('keydown', function (e) {
        // Enter при единственном найденном товаре — сразу в корзину (удобно со сканером штрихкода)
        if (e.key === 'Enter' && products.length === 1) {
            addToCart(products[0]);
            this.select();
        }
    });

    function loadProducts() {
        $('#products').addClass('loading');
        api('catalog', 'parts', Object.assign({ stock: 'in' }, filter), { silent: true }).then(res => {
            products = res.parts;
            // актуализируем остатки в корзине
            products.forEach(p => { if (cart[p.id]) cart[p.id].stock = Number(p.quantity); });
            if (!products.length) {
                $('#products').html(`<div style="grid-column:1/-1">${App.emptyState('search', 'Ничего не найдено')}</div>`);
                return;
            }
            $('#products').html(products.map((p, i) => `
                <div class="product" data-i="${i}">
                    <span class="code">${esc(p.code)}</span>
                    <span class="name">${esc(p.name)}</span>
                    <span class="stock"><span class="stock-dot ${p.stock_state}"></span>${p.quantity} шт. · ${esc(p.subcategory_name)}</span>
                    <span class="price">${money(p.selling_price)}</span>
                </div>`).join(''));
        }).finally(() => $('#products').removeClass('loading'));
    }

    $('#products').on('click', '.product', function () {
        addToCart(products[$(this).data('i')]);
    });

    // ---------- старт ----------
    $('#paymentMethod').html(App.selectOptions(APP.paymentMethods, 'Способ оплаты…'));
    renderChips();
    renderCart();
    loadProducts();
});
