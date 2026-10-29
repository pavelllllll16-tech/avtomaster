<?php
/**
 * Выгрузка отчётов в CSV (открывается в Excel).
 * Разделитель «;» и BOM — чтобы Excel правильно понял кириллицу и колонки.
 */
require_once __DIR__ . '/app/bootstrap.php';

$type = $_GET['type'] ?? '';
$from = query_date('date_from', date('Y-m-01'));
$to = query_date('date_to', date('Y-m-d'));
$n = function ($v) { return number_format((float)$v, 2, ',', ''); };

switch ($type) {
    case 'sales':
        $header = ['№ заказа', 'Дата', 'Клиент', 'Состав', 'Способ оплаты', 'Скидка, %', 'Сумма', 'Прибыль'];
        $rows = array_map(function ($o) use ($n) {
            $items = array_map(function ($i) { return "{$i['code']} {$i['name']} ({$i['quantity']} шт.)"; }, $o['items']);
            return [$o['id'], date('d.m.Y H:i', strtotime($o['created_at'])), $o['customer_name'], implode(', ', $items),
                    $o['payment_method'], $n($o['discount']), $n($o['final_amount']), $n($o['profit'])];
        }, ReportRepository::sales($from, $to));
        $title = "Продажи $from — $to";
        break;

    case 'profit':
        $header = ['Категория', 'Подкатегория', 'Продано, шт.', 'Выручка', 'Себестоимость', 'Прибыль'];
        $rows = array_map(function ($r) use ($n) {
            return [$r['category_name'], $r['subcategory_name'], $r['sold'], $n($r['revenue']), $n($r['cost']), $n($r['profit'])];
        }, ReportRepository::profit($from, $to));
        $title = "Прибыль $from — $to";
        break;

    case 'stock':
        $header = ['Артикул', 'Наименование', 'Категория', 'Подкатегория', 'Остаток', 'Мин. остаток', 'Закупка', 'Продажа', 'Наценка, %'];
        $rows = array_map(function ($p) use ($n) {
            return [$p['code'], $p['name'], $p['category_name'], $p['subcategory_name'], $p['quantity'], $p['min_quantity'],
                    $n($p['purchase_price']), $n($p['selling_price']), $p['markup'] === null ? '' : $n($p['markup'])];
        }, PartRepository::list([], 100000));
        $title = 'Остатки ' . date('Y-m-d');
        break;

    default:
        http_response_code(400);
        exit('Неизвестный тип отчёта');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($title . '.csv'));

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $header, ';');
foreach ($rows as $row) {
    fputcsv($out, $row, ';');
}
fclose($out);
