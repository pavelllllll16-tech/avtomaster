<?php
require_once __DIR__ . '/../app/bootstrap.php';

$handlers = [
    // ---------- поставщики ----------
    'suppliers' => function () {
        return ['suppliers' => SupplierRepository::list()];
    },

    'save_supplier' => function () {
        $id = (int)($_POST['id'] ?? 0);
        $name = input_str('name', 150, true, 'Название');
        $contact = input_str('contact_info', 255);
        if ($id > 0) {
            SupplierRepository::update($id, $name, $contact);
        } else {
            SupplierRepository::create($name, $contact);
        }
        return ['message' => 'Поставщик сохранён'];
    },

    'delete_supplier' => function () {
        SupplierRepository::delete(input_int('id', 1));
        return ['message' => 'Поставщик удалён'];
    },

    // ---------- приход товара ----------
    'next_number' => function () {
        return ['number' => SupplyRepository::nextInvoiceNumber()];
    },

    'create' => function () {
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!is_array($items)) {
            throw new AppException('Некорректный состав накладной');
        }
        $invoice = SupplyRepository::create([
            'supplier_id'        => input_int('supplier_id', 1, PHP_INT_MAX, 'Поставщик'),
            'invoice_number'     => input_str('invoice_number', 50),
            'supply_date'        => input_date('supply_date', 'Дата поставки'),
            'payment_delay_days' => input_int('payment_delay_days', 0, 365, 'Отсрочка'),
            'payment_method_id'  => input_id_or_null('payment_method_id'),
            'paid'               => !empty($_POST['paid']),
        ], $items);
        return ['message' => "Накладная № $invoice проведена, остатки обновлены", 'invoice' => $invoice];
    },

    // ---------- история накладных ----------
    'invoices' => function () {
        return [
            'invoices' => SupplyRepository::invoices([
                'supplier_id'    => $_POST['supplier_id'] ?? '',
                'date_from'      => query_date('date_from', ''),
                'date_to'        => query_date('date_to', ''),
                'payment_status' => $_POST['payment_status'] ?? '',
                'search'         => trim($_POST['search'] ?? ''),
            ]),
            'stats' => SupplyRepository::stats(),
        ];
    },

    'invoice' => function () {
        $number = input_str('invoice', 50, true);
        return [
            'items'   => SupplyRepository::invoiceItems($number),
            'returns' => SupplyRepository::returnsOf($number),
        ];
    },

    'set_paid' => function () {
        $paid = !empty($_POST['paid']);
        SupplyRepository::setPaid(input_str('invoice', 50, true), $paid);
        return ['message' => $paid ? 'Накладная отмечена как оплаченная' : 'Оплата снята'];
    },

    'return_item' => function () {
        SupplyRepository::returnItem(
            input_int('supply_id', 1),
            input_int('quantity', 1, 100000, 'Количество'),
            input_date('return_date', 'Дата возврата'),
            input_str('reason', 255)
        );
        return ['message' => 'Возврат оформлен, товар списан со склада'];
    },
];

api_dispatch($handlers);
