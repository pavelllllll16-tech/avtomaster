<?php
/**
 * Аналитика и отчёты. В расчётах учитываются только выполненные заказы (status = 1).
 *
 * Выручка позиции с учётом скидки заказа: oi.total_price * (1 - o.discount / 100)
 * Себестоимость позиции: oi.purchase_price * oi.quantity (закупочная цена на момент продажи)
 */
class ReportRepository
{
    private const REVENUE = 'oi.total_price * (1 - o.discount / 100)';
    private const COST = 'oi.purchase_price * oi.quantity';

    /** Показатели для главной страницы */
    public static function dashboard(): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $sales = Database::one(
            'SELECT
                COALESCE(SUM(CASE WHEN DATE(o.created_at) = ? THEN ' . self::REVENUE . ' END), 0) AS revenue_today,
                COALESCE(SUM(CASE WHEN o.created_at >= ? THEN ' . self::REVENUE . ' END), 0) AS revenue_month,
                COALESCE(SUM(CASE WHEN o.created_at >= ? THEN ' . self::REVENUE . ' - ' . self::COST . ' END), 0) AS profit_month,
                COUNT(DISTINCT CASE WHEN o.created_at >= ? THEN o.id END) AS orders_month
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.status = 1',
            [$today, $monthStart, $monthStart, $monthStart]
        );
        $stock = Database::one(
            'SELECT COUNT(*) AS positions,
                    SUM(quantity = 0) AS out_of_stock,
                    SUM(quantity > 0 AND quantity <= min_quantity) AS low_stock,
                    COALESCE(SUM(quantity * purchase_price), 0) AS stock_value
             FROM parts'
        );
        $openOrders = (int)Database::value('SELECT COUNT(*) FROM orders WHERE status = 0');
        $avgCheck = $sales['orders_month'] > 0 ? $sales['revenue_month'] / $sales['orders_month'] : 0;

        return array_merge($sales, $stock, SupplyRepository::stats(), [
            'open_orders' => $openOrders,
            'avg_check'   => $avgCheck,
        ]);
    }

    /** Выручка и прибыль по дням (для графика), дни без продаж заполняются нулями */
    public static function salesByDay(int $days = 30): array
    {
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $rows = Database::all(
            'SELECT DATE(o.created_at) AS day,
                    SUM(' . self::REVENUE . ') AS revenue,
                    SUM(' . self::REVENUE . ' - ' . self::COST . ') AS profit
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.status = 1 AND o.created_at >= ?
             GROUP BY DATE(o.created_at)',
            [$from]
        );
        $byDay = array_column($rows, null, 'day');
        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime("$from +$i days"));
            $result[] = [
                'day'     => $d,
                'revenue' => round((float)($byDay[$d]['revenue'] ?? 0), 2),
                'profit'  => round((float)($byDay[$d]['profit'] ?? 0), 2),
            ];
        }
        return $result;
    }

    public static function topParts(string $from, string $to, int $limit = 5): array
    {
        return Database::all(
            'SELECT p.code, p.name, SUM(oi.quantity) AS sold, SUM(' . self::REVENUE . ') AS revenue
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN parts p ON p.id = oi.part_id
             WHERE o.status = 1 AND o.created_at BETWEEN ? AND ?
             GROUP BY p.id
             ORDER BY revenue DESC
             LIMIT ' . (int)$limit,
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
    }

    /** Продажи по категориям — для круговой диаграммы */
    public static function revenueByCategory(string $from, string $to): array
    {
        return Database::all(
            'SELECT c.name, SUM(' . self::REVENUE . ') AS revenue
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN parts p ON p.id = oi.part_id
             JOIN subcategories s ON s.id = p.subcategory_id
             JOIN categories c ON c.id = s.category_id
             WHERE o.status = 1 AND o.created_at BETWEEN ? AND ?
             GROUP BY c.id
             ORDER BY revenue DESC',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
    }

    /** Отчёт о продажах: каждый выполненный заказ с составом */
    public static function sales(string $from, string $to): array
    {
        $orders = Database::all(
            'SELECT o.id, o.created_at, o.customer_name, o.discount, o.final_amount,
                    pm.name AS payment_method,
                    (SELECT SUM(oi.purchase_price * oi.quantity) FROM order_items oi WHERE oi.order_id = o.id) AS cost
             FROM orders o
             LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
             WHERE o.status = 1 AND o.created_at BETWEEN ? AND ?
             ORDER BY o.created_at',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        if (!$orders) {
            return [];
        }
        $ids = array_column($orders, 'id');
        $items = Database::all(
            'SELECT oi.order_id, oi.quantity, oi.price, p.code, p.name
             FROM order_items oi JOIN parts p ON p.id = oi.part_id
             WHERE oi.order_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );
        $byOrder = [];
        foreach ($items as $it) {
            $byOrder[$it['order_id']][] = $it;
        }
        foreach ($orders as &$o) {
            $o['items'] = $byOrder[$o['id']] ?? [];
            $o['profit'] = round($o['final_amount'] - $o['cost'], 2);
        }
        return $orders;
    }

    /** Прибыль по категориям и подкатегориям за период */
    public static function profit(string $from, string $to): array
    {
        return Database::all(
            'SELECT c.name AS category_name, s.name AS subcategory_name,
                    SUM(oi.quantity) AS sold,
                    SUM(' . self::REVENUE . ') AS revenue,
                    SUM(' . self::COST . ') AS cost,
                    SUM(' . self::REVENUE . ' - ' . self::COST . ') AS profit
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN parts p ON p.id = oi.part_id
             JOIN subcategories s ON s.id = p.subcategory_id
             JOIN categories c ON c.id = s.category_id
             WHERE o.status = 1 AND o.created_at BETWEEN ? AND ?
             GROUP BY c.id, s.id
             ORDER BY c.name, s.name',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
    }

    /** Складской отчёт: остатки и потенциальная прибыль */
    public static function stock(): array
    {
        return Database::all(
            'SELECT c.name AS category_name, s.name AS subcategory_name,
                    COUNT(p.id) AS positions,
                    COALESCE(SUM(p.quantity), 0) AS quantity,
                    COALESCE(SUM(p.quantity * p.purchase_price), 0) AS purchase_value,
                    COALESCE(SUM(p.quantity * p.selling_price), 0) AS selling_value,
                    COALESCE(SUM(p.quantity * (p.selling_price - p.purchase_price)), 0) AS potential_profit,
                    ROUND(AVG(CASE WHEN p.purchase_price > 0
                                   THEN (p.selling_price - p.purchase_price) / p.purchase_price * 100 END), 2) AS avg_markup
             FROM categories c
             JOIN subcategories s ON s.category_id = c.id
             LEFT JOIN parts p ON p.subcategory_id = s.id
             GROUP BY c.id, s.id
             ORDER BY c.name, s.name'
        );
    }

    public static function recentOrders(int $limit = 6): array
    {
        return Database::all(
            'SELECT o.id, o.created_at, o.customer_name, o.final_amount, o.status
             FROM orders o ORDER BY o.created_at DESC, o.id DESC LIMIT ' . (int)$limit
        );
    }
}
