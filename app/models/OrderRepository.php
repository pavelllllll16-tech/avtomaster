<?php
/**
 * Заказы покупателей и продажи.
 *
 * Жизненный цикл заказа:
 *   OPEN (0)      — заказ формируется, товар уже зарезервирован (списан со склада);
 *   COMPLETED (1) — продажа завершена, учитывается в отчётах;
 *   CANCELLED (2) — заказ отменён, товар возвращён на склад.
 */
class OrderRepository
{
    const OPEN = 0;
    const COMPLETED = 1;
    const CANCELLED = 2;

    const STATUS_NAMES = [
        self::OPEN      => 'Открыт',
        self::COMPLETED => 'Выполнен',
        self::CANCELLED => 'Отменён',
    ];

    /**
     * Список заказов с фильтрами и постраничным выводом.
     * Фильтры: search (№, клиент, примечание), part (артикул/название детали), status, date_from, date_to
     */
    public static function list(array $f, int $page = 1, int $perPage = 15): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['search'] ?? '') !== '') {
            $where[] = '(o.id = ? OR o.customer_name LIKE ? OR o.notes LIKE ?)';
            array_push($params, (int)$f['search'], "%{$f['search']}%", "%{$f['search']}%");
        }
        if (($f['part'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM order_items oi JOIN parts p ON p.id = oi.part_id
                                WHERE oi.order_id = o.id AND (p.name LIKE ? OR p.code LIKE ?))';
            array_push($params, "%{$f['part']}%", "%{$f['part']}%");
        }
        if (isset($f['status']) && $f['status'] !== '') {
            $where[] = 'o.status = ?';
            $params[] = (int)$f['status'];
        }
        if (!empty($f['date_from'])) {
            $where[] = 'o.created_at >= ?';
            $params[] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $where[] = 'o.created_at <= ?';
            $params[] = $f['date_to'] . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int)Database::value("SELECT COUNT(*) FROM orders o WHERE $whereSql", $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::all(
            "SELECT o.*, pm.name AS payment_method,
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS items_count
             FROM orders o
             LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
             WHERE $whereSql
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Заказ со всеми позициями. @return array|null */
    public static function find(int $id)
    {
        $order = Database::one(
            'SELECT o.*, pm.name AS payment_method
             FROM orders o
             LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
             WHERE o.id = ?',
            [$id]
        );
        if (!$order) {
            return null;
        }
        $order['items'] = Database::all(
            'SELECT oi.*, p.code, p.name, p.quantity AS stock
             FROM order_items oi JOIN parts p ON p.id = oi.part_id
             WHERE oi.order_id = ? ORDER BY oi.id',
            [$id]
        );
        $order['status_name'] = self::STATUS_NAMES[(int)$order['status']] ?? '?';
        return $order;
    }

    /**
     * Оформление продажи из корзины кассы.
     * Цены берутся из базы, а не от клиента — подменить цену в браузере нельзя.
     * $items = [['part_id' => 1, 'quantity' => 2, 'discount' => 0], ...]
     */
    public static function createSale(array $items, array $info): int
    {
        if (!$items) {
            throw new AppException('Корзина пуста');
        }
        if (empty($info['payment_method_id'])) {
            throw new AppException('Выберите способ оплаты');
        }

        return Database::transaction(function () use ($items, $info) {
            $orderId = Database::insert(
                'INSERT INTO orders (customer_name, discount, notes, payment_method_id, status)
                 VALUES (?, ?, ?, ?, ?)',
                [$info['customer_name'] ?: null, $info['discount'], $info['notes'] ?: null,
                 $info['payment_method_id'], self::COMPLETED]
            );
            foreach (self::mergeItems($items) as $item) {
                self::insertItem($orderId, $item['part_id'], $item['quantity'], $item['discount']);
            }
            self::recalc($orderId);
            return $orderId;
        });
    }

    /** Пустой открытый заказ (например, под заказ клиента по телефону) */
    public static function createOpen(string $customerName): int
    {
        return Database::insert(
            'INSERT INTO orders (customer_name, status) VALUES (?, ?)',
            [$customerName ?: null, self::OPEN]
        );
    }

    public static function addItem(int $orderId, int $partId, int $qty): void
    {
        Database::transaction(function () use ($orderId, $partId, $qty) {
            self::lockOpen($orderId);
            $existing = Database::one('SELECT id, quantity FROM order_items WHERE order_id = ? AND part_id = ?', [$orderId, $partId]);
            if ($existing) {
                self::changeItemQty((int)$existing['id'], (int)$existing['quantity'] + $qty);
            } else {
                self::insertItem($orderId, $partId, $qty, 0);
            }
            self::recalc($orderId);
        });
    }

    public static function updateItem(int $itemId, int $qty, float $discount): void
    {
        Database::transaction(function () use ($itemId, $qty, $discount) {
            $item = Database::one('SELECT * FROM order_items WHERE id = ?', [$itemId]);
            if (!$item) {
                throw new AppException('Позиция не найдена');
            }
            self::lockOpen((int)$item['order_id']);
            self::changeItemQty($itemId, $qty);
            Database::run(
                'UPDATE order_items SET discount = ?, total_price = ROUND(price * quantity * (1 - ? / 100), 2) WHERE id = ?',
                [$discount, $discount, $itemId]
            );
            self::recalc((int)$item['order_id']);
        });
    }

    public static function removeItem(int $itemId): void
    {
        Database::transaction(function () use ($itemId) {
            $item = Database::one('SELECT * FROM order_items WHERE id = ?', [$itemId]);
            if (!$item) {
                throw new AppException('Позиция не найдена');
            }
            self::lockOpen((int)$item['order_id']);
            PartRepository::returnToStock((int)$item['part_id'], (int)$item['quantity']);
            Database::run('DELETE FROM order_items WHERE id = ?', [$itemId]);
            self::recalc((int)$item['order_id']);
        });
    }

    /** Изменить клиента, примечание, скидку, способ оплаты (только у открытого заказа) */
    public static function updateInfo(int $orderId, array $info): void
    {
        Database::transaction(function () use ($orderId, $info) {
            self::lockOpen($orderId);
            Database::run(
                'UPDATE orders SET customer_name = ?, notes = ?, discount = ?, payment_method_id = ? WHERE id = ?',
                [$info['customer_name'] ?: null, $info['notes'] ?: null, $info['discount'], $info['payment_method_id'], $orderId]
            );
            self::recalc($orderId);
        });
    }

    public static function complete(int $orderId): void
    {
        Database::transaction(function () use ($orderId) {
            $order = self::lockOpen($orderId);
            if (!Database::value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$orderId])) {
                throw new AppException('В заказе нет ни одной позиции');
            }
            if (!$order['payment_method_id']) {
                throw new AppException('Укажите способ оплаты');
            }
            Database::run('UPDATE orders SET status = ? WHERE id = ?', [self::COMPLETED, $orderId]);
        });
    }

    /** Отмена: весь товар возвращается на склад */
    public static function cancel(int $orderId): void
    {
        Database::transaction(function () use ($orderId) {
            $order = Database::one('SELECT status FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$order) {
                throw new AppException('Заказ не найден');
            }
            if ((int)$order['status'] === self::CANCELLED) {
                throw new AppException('Заказ уже отменён');
            }
            foreach (Database::all('SELECT part_id, quantity FROM order_items WHERE order_id = ?', [$orderId]) as $item) {
                PartRepository::returnToStock((int)$item['part_id'], (int)$item['quantity']);
            }
            Database::run('UPDATE orders SET status = ? WHERE id = ?', [self::CANCELLED, $orderId]);
        });
    }

    /** Вернуть выполненный заказ в работу (для исправления ошибок) */
    public static function reopen(int $orderId): void
    {
        $n = Database::run('UPDATE orders SET status = ? WHERE id = ? AND status = ?', [self::OPEN, $orderId, self::COMPLETED])->rowCount();
        if ($n !== 1) {
            throw new AppException('Открыть заново можно только выполненный заказ');
        }
    }

    // ---------- внутренние методы ----------

    /** Блокирует строку заказа до конца транзакции и проверяет, что он открыт */
    private static function lockOpen(int $orderId): array
    {
        $order = Database::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$order) {
            throw new AppException('Заказ не найден');
        }
        if ((int)$order['status'] !== self::OPEN) {
            throw new AppException('Заказ закрыт — изменения невозможны');
        }
        return $order;
    }

    private static function insertItem(int $orderId, int $partId, int $qty, float $discount): void
    {
        if ($qty <= 0) {
            throw new AppException('Количество должно быть больше нуля');
        }
        $part = Database::one('SELECT id, name, selling_price, purchase_price FROM parts WHERE id = ? FOR UPDATE', [$partId]);
        if (!$part) {
            throw new AppException('Запчасть не найдена');
        }
        if (!PartRepository::takeFromStock($partId, $qty)) {
            throw new AppException('Недостаточно на складе: ' . $part['name']);
        }
        $total = round($part['selling_price'] * $qty * (1 - $discount / 100), 2);
        Database::run(
            'INSERT INTO order_items (order_id, part_id, quantity, price, purchase_price, discount, total_price)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $partId, $qty, $part['selling_price'], $part['purchase_price'], $discount, $total]
        );
    }

    /** Изменение количества с корректировкой складского остатка */
    private static function changeItemQty(int $itemId, int $newQty): void
    {
        if ($newQty <= 0) {
            throw new AppException('Количество должно быть больше нуля');
        }
        $item = Database::one('SELECT oi.*, p.name FROM order_items oi JOIN parts p ON p.id = oi.part_id WHERE oi.id = ?', [$itemId]);
        $diff = $newQty - (int)$item['quantity'];
        if ($diff > 0 && !PartRepository::takeFromStock((int)$item['part_id'], $diff)) {
            throw new AppException('Недостаточно на складе: ' . $item['name']);
        }
        if ($diff < 0) {
            PartRepository::returnToStock((int)$item['part_id'], -$diff);
        }
        Database::run(
            'UPDATE order_items SET quantity = ?, total_price = ROUND(price * ? * (1 - discount / 100), 2) WHERE id = ?',
            [$newQty, $newQty, $itemId]
        );
    }

    /** Пересчёт суммы заказа по позициям и общей скидке */
    private static function recalc(int $orderId): void
    {
        Database::run(
            'UPDATE orders o
             SET total_amount = (SELECT COALESCE(SUM(total_price), 0) FROM order_items WHERE order_id = o.id),
                 final_amount = ROUND((SELECT COALESCE(SUM(total_price), 0) FROM order_items WHERE order_id = o.id) * (1 - o.discount / 100), 2)
             WHERE o.id = ?',
            [$orderId]
        );
    }

    /** Одинаковые запчасти в корзине объединяются в одну позицию */
    private static function mergeItems(array $items): array
    {
        $merged = [];
        foreach ($items as $i) {
            $id = (int)($i['part_id'] ?? 0);
            $qty = (int)($i['quantity'] ?? 0);
            $discount = (float)($i['discount'] ?? 0);
            if ($id <= 0 || $qty <= 0) {
                throw new AppException('Некорректная позиция в корзине');
            }
            if ($discount < 0 || $discount > 100) {
                throw new AppException('Скидка на позицию должна быть от 0 до 100%');
            }
            if (isset($merged[$id])) {
                $merged[$id]['quantity'] += $qty;
            } else {
                $merged[$id] = ['part_id' => $id, 'quantity' => $qty, 'discount' => $discount];
            }
        }
        return array_values($merged);
    }
}
