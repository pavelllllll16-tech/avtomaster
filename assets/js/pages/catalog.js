/**
 * Склад и каталог: дерево категорий, список запчастей, редактирование.
 */
$(function () {
    const { esc, money, api } = App;
    let tree = [];
    let parts = [];
    let sel = { category_id: '', subcategory_id: '' };

    // ---------- дерево категорий ----------
    function loadTree() {
        return api('catalog', 'tree', {}, { silent: true }).then(res => {
            tree = res.categories;
            renderTree();
        });
    }

    function adminActions(kind, id, withAdd) {
        return `<span class="actions">
            ${withAdd ? `<button class="btn btn-sm btn-link text-reset add-sub" data-id="${id}" title="Добавить подкатегорию"><i class="bi bi-plus-lg"></i></button>` : ''}
            <button class="btn btn-sm btn-link text-reset rename" data-kind="${kind}" data-id="${id}" title="Переименовать"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-link text-danger remove" data-kind="${kind}" data-id="${id}" title="Удалить"><i class="bi bi-trash"></i></button>
        </span>`;
    }

    function renderTree() {
        const total = tree.reduce((s, c) => s + Number(c.parts_count), 0);
        let html = `<li><div class="tree-item ${!sel.category_id ? 'active' : ''}" data-cat="" data-sub="">
                        <i class="bi bi-grid"></i>Все запчасти<span class="count">${total}</span></div></li>`;
        tree.forEach(c => {
            const catActive = String(c.id) === sel.category_id && !sel.subcategory_id;
            html += `<li>
                <div class="tree-item ${catActive ? 'active' : ''}" data-cat="${c.id}" data-sub="">
                    <i class="bi bi-folder2"></i><span class="text-truncate">${esc(c.name)}</span>
                    <span class="count">${c.parts_count}</span>${adminActions('category', c.id, true)}
                </div>
                <ul class="tree-sub">${c.subcategories.map(s => `
                    <li><div class="tree-item ${String(s.id) === sel.subcategory_id ? 'active' : ''}" data-cat="${c.id}" data-sub="${s.id}">
                        <i class="bi bi-dot"></i><span class="text-truncate">${esc(s.name)}</span>
                        <span class="count">${s.parts_count}</span>${adminActions('subcategory', s.id, false)}
                    </div></li>`).join('')}
                </ul></li>`;
        });
        $('#tree').html(html);
    }

    $('#tree').on('click', '.tree-item', function (e) {
        if ($(e.target).closest('.actions').length) return;
        sel.category_id = String($(this).data('cat'));
        sel.subcategory_id = String($(this).data('sub'));
        $('#listTitle').text($(this).find('.text-truncate').first().text() || 'Все запчасти');
        renderTree();
        loadParts();
    });

    // ---------- список запчастей ----------
    function loadParts() {
        $('#partsBody').addClass('loading');
        api('catalog', 'parts', Object.assign({}, sel, {
            search: $('#search').val().trim(),
            stock: $('#stockFilter').val(),
        }), { silent: true }).then(res => {
            parts = res.parts;
            $('#listCount').text('· ' + parts.length + ' поз.');
            if (!parts.length) {
                $('#partsBody').html(`<tr><td colspan="8">${App.emptyState('box', 'Запчасти не найдены')}</td></tr>`);
                return;
            }
            $('#partsBody').html(parts.map((p, i) => `
                <tr data-i="${i}">
                    <td class="fw-semibold text-nowrap">${esc(p.code)}</td>
                    <td>${esc(p.name)}</td>
                    <td class="small text-secondary">${esc(p.category_name)} / ${esc(p.subcategory_name)}</td>
                    <td>${App.stockBadge(p)}</td>
                    <td class="num">${money(p.purchase_price)}</td>
                    <td class="num fw-semibold">${money(p.selling_price)}</td>
                    <td class="num">${p.markup == null ? '—' : p.markup + '%'}</td>
                    <td class="text-nowrap text-end">
                        <button class="btn btn-sm btn-link text-reset edit-part" title="Изменить"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-link text-danger delete-part" title="Удалить"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join(''));
        }).finally(() => $('#partsBody').removeClass('loading'));
    }

    $('#search').on('input', App.debounce(loadParts, 300));
    $('#stockFilter').on('change', loadParts);

    // ---------- редактирование ----------
    {
        const partModal = new bootstrap.Modal('#partModal');
        const nameModal = new bootstrap.Modal('#nameModal');
        const $pf = $('#partForm');

        function fillSubcategories(catId, selected) {
            const cat = tree.find(c => String(c.id) === String(catId));
            $('#partSubcategory').html(App.selectOptions(cat ? cat.subcategories : [], 'Выберите…', selected));
        }

        function updateMarkup() {
            const buy = Number($pf.find('[name=purchase_price]').val());
            const sell = Number($pf.find('[name=selling_price]').val());
            $('#markupView').text(buy > 0 && sell ? ((sell - buy) / buy * 100).toFixed(1) + '%  (+' + money(sell - buy) + ')' : '—');
        }

        function openPart(p) {
            App.fillForm($pf, p || { quantity: 0, min_quantity: 2 });
            const catId = p ? p.category_id : (sel.category_id || '');
            $('#partCategory').html(App.selectOptions(tree, 'Выберите…', catId));
            fillSubcategories(catId, p ? p.subcategory_id : sel.subcategory_id);
            $('#partModalTitle').text(p ? 'Редактирование запчасти' : 'Новая запчасть');
            updateMarkup();
            partModal.show();
        }

        $('#addPart').on('click', () => openPart(null));
        $('#partsBody').on('click', '.edit-part', function () {
            openPart(parts[$(this).closest('tr').data('i')]);
        }).on('click', '.delete-part', function () {
            const p = parts[$(this).closest('tr').data('i')];
            App.confirm(`Удалить «${p.name}»?`, 'Удалить', 'btn-danger').then(ok => {
                if (ok) api('catalog', 'delete_part', { id: p.id }).then(() => { loadParts(); loadTree(); });
            });
        });

        $('#partCategory').on('change', function () { fillSubcategories(this.value); });
        $pf.on('input', '[name=purchase_price], [name=selling_price]', updateMarkup);
        $pf.on('click', '.quick-markup', function () {
            const buy = Number($pf.find('[name=purchase_price]').val());
            if (!buy) { App.toast('Сначала укажите закупочную цену', 'warning'); return; }
            $pf.find('[name=selling_price]').val((buy * (1 + $(this).data('m') / 100)).toFixed(2));
            updateMarkup();
        });
        $pf.on('submit', function (e) {
            e.preventDefault();
            api('catalog', 'save_part', App.formData($pf)).then(() => {
                partModal.hide();
                loadParts();
                loadTree();
            });
        });

        // категории
        function openName(kind, id, categoryId, name) {
            const $f = $('#nameForm');
            App.fillForm($f, { kind, id: id || '', category_id: categoryId || '', name: name || '' });
            $('#nameModalTitle').text((id ? 'Переименовать ' : 'Новая ') + (kind === 'category' ? 'категорию' : 'подкатегорию'));
            nameModal.show();
            setTimeout(() => $f.find('[name=name]').trigger('focus'), 300);
        }

        function findName(kind, id) {
            for (const c of tree) {
                if (kind === 'category' && c.id == id) return c.name;
                for (const s of c.subcategories) if (kind === 'subcategory' && s.id == id) return s.name;
            }
            return '';
        }

        $('#addCategory').on('click', () => openName('category'));
        $('#tree')
            .on('click', '.add-sub', function () { openName('subcategory', null, $(this).data('id')); })
            .on('click', '.rename', function () {
                const kind = $(this).data('kind'), id = $(this).data('id');
                openName(kind, id, null, findName(kind, id));
            })
            .on('click', '.remove', function () {
                const kind = $(this).data('kind'), id = $(this).data('id');
                App.confirm(`Удалить «${findName(kind, id)}»?`, 'Удалить', 'btn-danger').then(ok => {
                    if (!ok) return;
                    api('catalog', 'delete_' + kind, { id }).then(() => {
                        sel = { category_id: '', subcategory_id: '' };
                        loadTree();
                        loadParts();
                    });
                });
            });

        $('#nameForm').on('submit', function (e) {
            e.preventDefault();
            const d = App.formData($(this));
            api('catalog', 'save_' + d.kind, d).then(() => {
                nameModal.hide();
                loadTree();
            });
        });
    }

    // ---------- старт ----------
    $('#stockFilter').val(APP.stock);
    loadTree();
    loadParts();
});
