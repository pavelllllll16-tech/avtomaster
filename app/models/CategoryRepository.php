<?php
/**
 * Категории и подкатегории запчастей.
 */
class CategoryRepository
{
    /** Дерево: категории со вложенными подкатегориями и количеством запчастей */
    public static function tree(): array
    {
        $categories = Database::all('SELECT id, name FROM categories ORDER BY name');
        $subs = Database::all(
            'SELECT s.id, s.category_id, s.name, COUNT(p.id) AS parts_count
             FROM subcategories s
             LEFT JOIN parts p ON p.subcategory_id = s.id
             GROUP BY s.id
             ORDER BY s.name'
        );
        $byCategory = [];
        foreach ($subs as $s) {
            $byCategory[$s['category_id']][] = $s;
        }
        foreach ($categories as &$c) {
            $c['subcategories'] = $byCategory[$c['id']] ?? [];
            $c['parts_count'] = array_sum(array_column($c['subcategories'], 'parts_count'));
        }
        return $categories;
    }

    public static function createCategory(string $name): int
    {
        self::assertUniqueCategory($name);
        return Database::insert('INSERT INTO categories (name) VALUES (?)', [$name]);
    }

    public static function renameCategory(int $id, string $name): void
    {
        self::assertUniqueCategory($name, $id);
        Database::run('UPDATE categories SET name = ? WHERE id = ?', [$name, $id]);
    }

    public static function deleteCategory(int $id): void
    {
        if (Database::value('SELECT COUNT(*) FROM subcategories WHERE category_id = ?', [$id]) > 0) {
            throw new AppException('Сначала удалите подкатегории этой категории');
        }
        Database::run('DELETE FROM categories WHERE id = ?', [$id]);
    }

    public static function createSubcategory(int $categoryId, string $name): int
    {
        if (!Database::value('SELECT 1 FROM categories WHERE id = ?', [$categoryId])) {
            throw new AppException('Категория не найдена');
        }
        self::assertUniqueSub($categoryId, $name);
        return Database::insert('INSERT INTO subcategories (category_id, name) VALUES (?, ?)', [$categoryId, $name]);
    }

    public static function renameSubcategory(int $id, string $name): void
    {
        $categoryId = (int)Database::value('SELECT category_id FROM subcategories WHERE id = ?', [$id]);
        if (!$categoryId) {
            throw new AppException('Подкатегория не найдена');
        }
        self::assertUniqueSub($categoryId, $name, $id);
        Database::run('UPDATE subcategories SET name = ? WHERE id = ?', [$name, $id]);
    }

    public static function deleteSubcategory(int $id): void
    {
        if (Database::value('SELECT COUNT(*) FROM parts WHERE subcategory_id = ?', [$id]) > 0) {
            throw new AppException('В подкатегории есть запчасти — сначала перенесите или удалите их');
        }
        Database::run('DELETE FROM subcategories WHERE id = ?', [$id]);
    }

    private static function assertUniqueCategory(string $name, int $exceptId = 0): void
    {
        if (Database::value('SELECT 1 FROM categories WHERE name = ? AND id <> ?', [$name, $exceptId])) {
            throw new AppException('Категория с таким названием уже существует');
        }
    }

    private static function assertUniqueSub(int $categoryId, string $name, int $exceptId = 0): void
    {
        if (Database::value('SELECT 1 FROM subcategories WHERE category_id = ? AND name = ? AND id <> ?', [$categoryId, $name, $exceptId])) {
            throw new AppException('Такая подкатегория в этой категории уже есть');
        }
    }
}
