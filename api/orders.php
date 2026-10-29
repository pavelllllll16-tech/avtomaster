<?php
require_once __DIR__ . '/../app/bootstrap.php';

/** Вернуть свежие данные заказа после изменения */
function order_payload(int $id, string $message): array
{
    return ['message' => $message, 'order' => OrderRepository::find($id)];
}

api_dispatch([
    // Оформление продажи на кассе
    'checkout' => function () {
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!is_array($items)) {
            throw new AppException('Некорректные данные корзины');
        }
        $id = OrderRepository::createSale($items, [
            'customer_name'     => input_str('customer_name', 150),
            'notes'             => input_str('notes', 1000),
            'discount'          => input_float('discount', 0, 100, 'Скидка'),
            'payment_method_id' => input_id_or_null('payment_method_id'),
        ]);
        return ['message' => "Продажа оформлена, заказ № $id", 'order_id' => $id];
    },

    'list' => function () {
        return OrderRepository::list([
            'search'    => trim($_POST['search'] ?? ''),
            'part'      => trim($_POST['part'] ?? ''),
            'status'    => $_POST['status'] ?? '',
            'date_from' => query_date('date_from', ''),
            'date_to'   => query_date('date_to', ''),
        ], (int)($_POST['page'] ?? 1));
    },

    'get' => function () {
        $order = OrderRepository::find(input_int('id', 1));
        if (!$order) {
            throw new AppException('Заказ не найден');
        }
        return ['order' => $order];
    },

    'create_open' => function () {
        $id = OrderRepository::createOpen(input_str('customer_name', 150));
        return order_payload($id, "Создан заказ № $id");
    },

    'add_item' => function () {
        $id = input_int('order_id', 1);
        OrderRepository::addItem($id, input_int('part_id', 1), input_int('quantity', 1, 100000, 'Количество'));
        return order_payload($id, 'Позиция добавлена');
    },

    'update_item' => function () {
        OrderRepository::updateItem(
            input_int('item_id', 1),
            input_int('quantity', 1, 100000, 'Количество'),
            input_float('discount', 0, 100, 'Скидка')
        );
        return order_payload(input_int('order_id', 1), 'Позиция изменена');
    },

    'remove_item' => function () {
        OrderRepository::removeItem(input_int('item_id', 1));
        return order_payload(input_int('order_id', 1), 'Позиция удалена, товар возвращён на склад');
    },

    'update_info' => function () {
        $id = input_int('order_id', 1);
        OrderRepository::updateInfo($id, [
            'customer_name'     => input_str('customer_name', 150),
            'notes'             => input_str('notes', 1000),
            'discount'          => input_float('discount', 0, 100, 'Скидка'),
            'payment_method_id' => input_id_or_null('payment_method_id'),
        ]);
        return order_payload($id, 'Данные заказа сохранены');
    },

    'complete' => function () {
        $id = input_int('order_id', 1);
        OrderRepository::complete($id);
        return order_payload($id, 'Заказ выполнен');
    },

    'cancel' => function () {
        $id = input_int('order_id', 1);
        OrderRepository::cancel($id);
        return order_payload($id, 'Заказ отменён, товар возвращён на склад');
    },

    'reopen' => function () {
        $id = input_int('order_id', 1);
        OrderRepository::reopen($id);
        return order_payload($id, 'Заказ снова открыт для изменений');
    },
]);
