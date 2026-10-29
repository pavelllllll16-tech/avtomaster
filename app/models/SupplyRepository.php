<?php
/**
 * Поставки (приходные накладные), оплата и возвраты поставщику.
 */
class SupplyRepository
{
    /** Подзапрос: сколько единиц по каждой строке поставки уже возвращено */
    private const RETURNED_JOIN = 'LEFT JOIN (SELECT supply_id, SUM(quantity) AS returned
                                              FROM supplier_returns GROUP BY supply_id) r ON r.supply_id = sp.id';

    /**
     * Оформление приходной накладной.
     * $head: supplier_id, invoice_number, supply_date, payment_delay_days, payment_method_id, paid
     * $items: [['part_id', 'quantity', 'purchase_price', 'selling_price'], ...]
     */
    public static function create(array $head, array $items): string
    {
        if (!$items) {
            throw new AppException('Добавьте в накладную хотя бы одну позицию');
        }
        if (!Database::value('SELECT 1 FROM suppliers WHERE id = ?', [$head['supplier_id']])) {
            throw new AppException('Выберите поставщика');
        }

        return Database::transaction(function () use ($head, $items) {
            $invoice = $head['invoice_number'] !== '' ? $head['invoice_number'] : self::nextInvoiceNumber();
            if (Database::value('SELECT 1 FROM supplier_parts WHERE invoice_number = ? LIMIT 1', [$invoice])) {
                throw new AppException("Накладная № $invoice уже существует");
            }

            foreach ($items as $i) {
                $partId = (int)($i['part_id'] ?? 0);
                $qty = (int)($i['quantity'] ?? 0);
                $buy = round((float)($i['purchase_price'] ?? 0), 2);
                $sell = round((float)($i['selling_price'] ?? 0), 2);
                if ($qty <= 0 || $buy < 0 || $sell < 0) {
                    throw new AppException('Проверьте количество и цены в накладной');
                }
                $n = Database::run(
                    'UPDATE parts SET quantity = quantity + ?, purchase_price = ?, selling_price = ? WHERE id = ?',
                    [$qty, $buy, $sell, $partId]
                )->rowCount();
                if ($n === 0 && !Database::value('SELECT 1 FROM parts WHERE id = ?', [$partId])) {
                    throw new AppException('Запчасть не найдена');
                }
                Database::run(
                    'INSERT INTO supplier_parts (supplier_id, part_id, quantity, price, payment_delay_days,
                                                 payment_method_id, supply_date, invoice_number, payment_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$head['supplier_id'], $partId, $qty, $buy, $head['payment_delay_days'],
                     $head['payment_method_id'], $head['supply_date'], $invoice, $head['paid'] ? 'paid' : 'unpaid']
                );
            }
            return $invoice;
        });
    }

    public static function nextInvoiceNumber(): string
    {
        $n = (int)Database::value('SELECT COUNT(DISTINCT invoice_number) FROM supplier_parts') + 1;
        do {
            $number = sprintf('ПН-%04d', $n++);
        } while (Database::value('SELECT 1 FROM supplier_parts WHERE invoice_number = ? LIMIT 1', [$number]));
        return $number;
    }

    /**
     * Список накладных (сгруппировано по номеру).
     * Фильтры: supplier_id, date_from, date_to, payment_status (paid|unpaid|overdue), search (№ или запчасть)
     */
    public static function invoices(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['supplier_id'])) {
            $where[] = 'sp.supplier_id = ?';
            $params[] = (int)$f['supplier_id'];
        }
        if (!empty($f['date_from'])) {
            $where[] = 'sp.supply_date >= ?';
            $params[] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[] = 'sp.supply_date <= ?';
            $params[] = $f['date_to'];
        }
        if (($f['search'] ?? '') !== '') {
            $where[] = 'sp.invoice_number IN (SELECT sp2.invoice_number FROM supplier_parts sp2 JOIN parts p ON p.id = sp2.part_id
                                              WHERE sp2.invoice_number LIKE ? OR p.name LIKE ? OR p.code LIKE ?)';
            array_push($params, "%{$f['search']}%", "%{$f['search']}%", "%{$f['search']}%");
        }
        $having = '';
        switch ($f['payment_status'] ?? '') {
            case 'paid':    $having = "HAVING payment_status = 'paid'"; break;
            case 'unpaid':  $having = "HAVING payment_status = 'unpaid'"; break;
            case 'overdue': $having = "HAVING payment_status = 'unpaid' AND due_date < CURDATE()"; break;
        }

        return Database::all(
            'SELECT sp.invoice_number,
                    MAX(s.id) AS supplier_id, MAX(s.name) AS supplier_name,
                    MIN(sp.supply_date) AS supply_date,
                    MAX(sp.payment_delay_days) AS payment_delay_days,
                    DATE_ADD(MIN(sp.supply_date), INTERVAL MAX(sp.payment_delay_days) DAY) AS due_date,
                    MAX(pm.name) AS payment_method,
                    COUNT(*) AS positions,
                    SUM(sp.quantity) AS quantity,
                    SUM(sp.quantity * sp.price) AS total_amount,
                    SUM(COALESCE(r.returned, 0) * sp.price) AS returned_amount,
                    SUM((sp.quantity - COALESCE(r.returned, 0)) * sp.price) AS amount_due,
                    CASE WHEN SUM(sp.payment_status = \'unpaid\') > 0 THEN \'unpaid\' ELSE \'paid\' END AS payment_status
             FROM supplier_parts sp
             JOIN suppliers s ON s.id = sp.supplier_id
             LEFT JOIN payment_methods pm ON pm.id = sp.payment_method_id
             ' . self::RETURNED_JOIN . '
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY sp.invoice_number
             ' . $having . '
             ORDER BY supply_date DESC, sp.invoice_number DESC',
            $params
        );
    }

    /** Позиции накладной с учётом возвратов */
    public static function invoiceItems(string $invoice): array
    {
        return Database::all(
            'SELECT sp.*, p.code, p.name, p.quantity AS stock,
                    COALESCE(r.returned, 0) AS returned,
                    sp.quantity - COALESCE(r.returned, 0) AS remaining
             FROM supplier_parts sp
             JOIN parts p ON p.id = sp.part_id
             ' . self::RETURNED_JOIN . '
             WHERE sp.invoice_number = ?
             ORDER BY sp.id',
            [$invoice]
        );
    }

    public static function returnsOf(string $invoice): array
    {
        return Database::all(
            'SELECT sr.*, p.name, p.code, sp.price
             FROM supplier_returns sr
             JOIN supplier_parts sp ON sp.id = sr.supply_id
             JOIN parts p ON p.id = sp.part_id
             WHERE sp.invoice_number = ?
             ORDER BY sr.return_date, sr.id',
            [$invoice]
        );
    }

    public static function setPaid(string $invoice, bool $paid): void
    {
        $n = Database::run('UPDATE supplier_parts SET payment_status = ? WHERE invoice_number = ?', [$paid ? 'paid' : 'unpaid', $invoice])->rowCount();
        if ($n === 0 && !Database::value('SELECT 1 FROM supplier_parts WHERE invoice_number = ? LIMIT 1', [$invoice])) {
            throw new AppException('Накладная не найдена');
        }
    }

    /** Возврат поставщику: товар списывается со склада, сумма долга уменьшается */
    public static function returnItem(int $supplyId, int $qty, string $date, string $reason): void
    {
        if ($qty <= 0) {
            throw new AppException('Количество должно быть больше нуля');
        }
        Database::transaction(function () use ($supplyId, $qty, $date, $reason) {
            $row = Database::one('SELECT * FROM supplier_parts WHERE id = ? FOR UPDATE', [$supplyId]);
            if (!$row) {
                throw new AppException('Позиция поставки не найдена');
            }
            if ($date < $row['supply_date']) {
                throw new AppException('Дата возврата раньше даты поставки');
            }
            $returned = (int)Database::value('SELECT COALESCE(SUM(quantity), 0) FROM supplier_returns WHERE supply_id = ?', [$supplyId]);
            if ($qty > (int)$row['quantity'] - $returned) {
                throw new AppException('Можно вернуть не более ' . ((int)$row['quantity'] - $returned) . ' шт.');
            }
            if (!PartRepository::takeFromStock((int)$row['part_id'], $qty)) {
                throw new AppException('На складе меньше товара, чем нужно вернуть (часть уже продана)');
            }
            Database::run(
                'INSERT INTO supplier_returns (supply_id, return_date, quantity, reason) VALUES (?, ?, ?, ?)',
                [$supplyId, $date, $qty, $reason ?: null]
            );
        });
    }

    /** Сводка для карточек вверху страницы */
    public static function stats(): array
    {
        return Database::one(
            'SELECT COUNT(DISTINCT sp.invoice_number) AS invoices,
                    COALESCE(SUM(sp.quantity * sp.price), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN sp.payment_status = \'unpaid\'
                                      THEN (sp.quantity - COALESCE(r.returned, 0)) * sp.price END), 0) AS unpaid_amount,
                    COALESCE(SUM(CASE WHEN sp.payment_status = \'unpaid\'
                                       AND DATE_ADD(sp.supply_date, INTERVAL sp.payment_delay_days DAY) < CURDATE()
                                      THEN (sp.quantity - COALESCE(r.returned, 0)) * sp.price END), 0) AS overdue_amount
             FROM supplier_parts sp ' . self::RETURNED_JOIN
        );
    }
}
