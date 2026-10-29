<?php
/**
 * Запчасти (номенклатура и складские остатки).
 */
class PartRepository
{
    private const SELECT = 'SELECT p.*, s.name AS subcategory_name, s.category_id, c.name AS category_name
                            FROM parts p
                            JOIN subcategories s ON s.id = p.subcategory_id
                            JOIN categories c ON c.id = s.category_id';

    /**
     * Список с фильтрами:
     * category_id, subcategory_id, search (артикул/название), stock = all|in|low|out|need, ids (массив id)
     */
    public static function list(array $f = [], int $limit = 500): array
    {
        $where = [];
        $params = [];
        if (!empty($f['subcategory_id'])) {
            $where[] = 'p.subcategory_id = ?';
            $params[] = (int)$f['subcategory_id'];
        } elseif (!empty($f['category_id'])) {
            $where[] = 's.category_id = ?';
            $params[] = (int)$f['category_id'];
        }
        if (!empty($f['search'])) {
            $where[] = '(p.code LIKE ? OR p.name LIKE ?)';
            $params[] = '%' . $f['search'] . '%';
            $params[] = '%' . $f['search'] . '%';
        }
        switch ($f['stock'] ?? 'all') {
            case 'in':  $where[] = 'p.quantity > 0'; break;
            case 'low': $where[] = 'p.quantity > 0 AND p.quantity <= p.min_quantity'; break;
            case 'out': $where[] = 'p.quantity = 0'; break;
            case 'need': $where[] = 'p.quantity <= p.min_quantity'; break;
        }
        if (!empty($f['ids'])) {
            $ids = array_map('intval', (array)$f['ids']);
            $where[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }

        $sql = self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY c.name, s.name, p.name LIMIT ' . (int)$limit;

        return array_map([self::class, 'decorate'], Database::all($sql, $params));
    }

    /** @return array|null */
    public static function find(int $id)
    {
        $row = Database::one(self::SELECT . ' WHERE p.id = ?', [$id]);
        return $row ? self::decorate($row) : null;
    }

    public static function create(array $d): int
    {
        self::validate($d);
        return Database::insert(
            'INSERT INTO parts (subcategory_id, code, name, quantity, min_quantity, purchase_price, selling_price)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['subcategory_id'], $d['code'], $d['name'], $d['quantity'], $d['min_quantity'], $d['purchase_price'], $d['selling_price']]
        );
    }

    public static function update(int $id, array $d): void
    {
        if (!self::find($id)) {
            throw new AppException('Запчасть не найдена');
        }
        self::validate($d, $id);
        Database::run(
            'UPDATE parts SET subcategory_id = ?, code = ?, name = ?, quantity = ?, min_quantity = ?,
                              purchase_price = ?, selling_price = ?
             WHERE id = ?',
            [$d['subcategory_id'], $d['code'], $d['name'], $d['quantity'], $d['min_quantity'], $d['purchase_price'], $d['selling_price'], $id]
        );
    }

    public static function delete(int $id): void
    {
        $used = Database::value('SELECT (SELECT COUNT(*) FROM order_items WHERE part_id = ?) + (SELECT COUNT(*) FROM supplier_parts WHERE part_id = ?)', [$id, $id]);
        if ($used > 0) {
            throw new AppException('Запчасть есть в заказах или поставках — удалить нельзя, можно только обнулить остаток');
        }
        Database::run('DELETE FROM parts WHERE id = ?', [$id]);
    }

    /** Списать со склада. Возвращает false, если товара не хватает */
    public static function takeFromStock(int $partId, int $qty): bool
    {
        return Database::run(
            'UPDATE parts SET quantity = quantity - ? WHERE id = ? AND quantity >= ?',
            [$qty, $partId, $qty]
        )->rowCount() === 1;
    }

    public static function returnToStock(int $partId, int $qty): void
    {
        Database::run('UPDATE parts SET quantity = quantity + ? WHERE id = ?', [$qty, $partId]);
    }

    private static function validate(array $d, int $exceptId = 0): void
    {
        if (!Database::value('SELECT 1 FROM subcategories WHERE id = ?', [$d['subcategory_id']])) {
            throw new AppException('Выберите подкатегорию');
        }
        if (Database::value('SELECT 1 FROM parts WHERE code = ? AND id <> ?', [$d['code'], $exceptId])) {
            throw new AppException('Запчасть с артикулом «' . $d['code'] . '» уже существует');
        }
        if ($d['selling_price'] < $d['purchase_price']) {
            throw new AppException('Цена продажи ниже закупочной');
        }
    }

    private static function decorate(array $p): array
    {
        $p['markup'] = markup_percent($p['purchase_price'], $p['selling_price']);
        $p['stock_state'] = (int)$p['quantity'] === 0 ? 'out'
            : ((int)$p['quantity'] <= (int)$p['min_quantity'] ? 'low' : 'ok');
        return $p;
    }
}
