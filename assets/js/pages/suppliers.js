/**
 * Справочник поставщиков.
 */
$(function () {
    const { esc, money, api, date } = App;
    const modal = new bootstrap.Modal('#supplierModal');
    let suppliers = [];

    function load() {
        api('supplies', 'suppliers', {}, { silent: true }).then(res => {
            suppliers = res.suppliers;
            render();
        });
    }

    function render() {
        const q = $('#search').val().trim().toLowerCase();
        const list = suppliers.filter(s => !q || (s.name + ' ' + (s.contact_info || '')).toLowerCase().includes(q));
        if (!list.length) {
            $('#suppliersBody').html(`<tr><td colspan="7">${App.emptyState('building', 'Поставщики не найдены')}</td></tr>`);
            return;
        }
        $('#suppliersBody').html(list.map(s => `
            <tr data-id="${s.id}">
                <td class="fw-semibold">${esc(s.name)}</td>
                <td class="small text-secondary">${esc(s.contact_info || '—')}</td>
                <td class="text-center">${s.invoices_count}</td>
                <td>${s.last_supply ? date(s.last_supply) : '—'}</td>
                <td class="num">${money(s.total_amount)}</td>
                <td class="num ${Number(s.debt) > 0 ? 'text-danger fw-semibold' : ''}">${money(s.debt)}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-link text-reset" href="supplies.php?tab=history" title="Накладные"><i class="bi bi-journal-text"></i></a>
                    <button class="btn btn-sm btn-link text-reset edit" title="Изменить"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-link text-danger remove" title="Удалить"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`).join(''));
    }

    function open(s) {
        App.fillForm($('#supplierForm'), s || {});
        $('#supplierTitle').text(s ? 'Редактирование поставщика' : 'Новый поставщик');
        modal.show();
    }

    const byId = id => suppliers.find(s => s.id == id);

    $('#search').on('input', render);
    $('#addSupplier').on('click', () => open(null));
    $('#suppliersBody')
        .on('click', '.edit', function () { open(byId($(this).closest('tr').data('id'))); })
        .on('click', '.remove', function () {
            const s = byId($(this).closest('tr').data('id'));
            App.confirm(`Удалить поставщика «${s.name}»?`, 'Удалить', 'btn-danger').then(ok => {
                if (ok) api('supplies', 'delete_supplier', { id: s.id }).then(load);
            });
        });

    $('#supplierForm').on('submit', function (e) {
        e.preventDefault();
        const d = App.formData($(this));
        api('supplies', 'save_supplier', { id: d.id, name: d.name, contact_info: d.contact_info }).then(() => {
            modal.hide();
            load();
        });
    });

    load();
});
