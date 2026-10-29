<?php
/**
 * Поставщики.
 */
class SupplierRepository
{
    /** Поставщики со сводкой по закупкам и долгу */
    public static function list(): array
    {
        return Database::all(
            'SELECT s.*,
                    COUNT(DISTINCT sp.invoice_number) AS invoices_count,
                    COALESCE(SUM(sp.quantity * sp.price), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN sp.payment_status = \'unpaid\'
                                      THEN (sp.quantity - COALESCE(r.returned, 0)) * sp.price END), 0) AS debt,
                    MAX(sp.supply_date) AS last_supply
             FROM suppliers s
             LEFT JOIN supplier_parts sp ON sp.supplier_id = s.id
             LEFT JOIN (SELECT supply_id, SUM(quantity) AS returned FROM supplier_returns GROUP BY supply_id) r
                    ON r.supply_id = sp.id
             GROUP BY s.id
             ORDER BY s.name'
        );
    }

    public static function options(): array
    {
        return Database::all('SELECT id, name FROM suppliers ORDER BY name');
    }

    public static function create(string $name, string $contact): int
    {
        self::assertUnique($name);
        return Database::insert('INSERT INTO suppliers (name, contact_info) VALUES (?, ?)', [$name, $contact ?: null]);
    }

    public static function update(int $id, string $name, string $contact): void
    {
        self::assertUnique($name, $id);
        Database::run('UPDATE suppliers SET name = ?, contact_info = ? WHERE id = ?', [$name, $contact ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        if (Database::value('SELECT COUNT(*) FROM supplier_parts WHERE supplier_id = ?', [$id])) {
            throw new AppException('У поставщика есть поставки — удалить нельзя');
        }
        Database::run('DELETE FROM suppliers WHERE id = ?', [$id]);
    }

    private static function assertUnique(string $name, int $exceptId = 0): void
    {
        if (Database::value('SELECT 1 FROM suppliers WHERE name = ? AND id <> ?', [$name, $exceptId])) {
            throw new AppException('Поставщик с таким названием уже есть');
        }
    }
}
