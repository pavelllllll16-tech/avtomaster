<?php
require_once __DIR__ . '/../app/bootstrap.php';

/** Данные запчасти из формы */
function part_from_request(): array
{
    return [
        'subcategory_id' => input_int('subcategory_id', 1, PHP_INT_MAX, 'Подкатегория'),
        'code'           => input_str('code', 50, true, 'Артикул'),
        'name'           => input_str('name', 200, true, 'Наименование'),
        'quantity'       => input_int('quantity', 0, 1000000, 'Количество'),
        'min_quantity'   => input_int('min_quantity', 0, 100000, 'Мин. остаток'),
        'purchase_price' => input_float('purchase_price', 0, 1e8, 'Закупочная цена'),
        'selling_price'  => input_float('selling_price', 0, 1e8, 'Цена продажи'),
    ];
}

api_dispatch([
    'tree' => function () {
        return ['categories' => CategoryRepository::tree()];
    },

    'parts' => function () {
        return ['parts' => PartRepository::list([
            'category_id'    => $_POST['category_id'] ?? null,
            'subcategory_id' => $_POST['subcategory_id'] ?? null,
            'search'         => trim($_POST['search'] ?? ''),
            'stock'          => $_POST['stock'] ?? 'all',
            'ids'            => $_POST['ids'] ?? [],
        ])];
    },

    'save_part' => function () {
        $id = (int)($_POST['id'] ?? 0);
        $data = part_from_request();
        if ($id > 0) {
            PartRepository::update($id, $data);
            return ['message' => 'Запчасть сохранена'];
        }
        PartRepository::create($data);
        return ['message' => 'Запчасть добавлена'];
    },

    'delete_part' => function () {
        PartRepository::delete(input_int('id', 1));
        return ['message' => 'Запчасть удалена'];
    },

    'save_category' => function () {
        $id = (int)($_POST['id'] ?? 0);
        $name = input_str('name', 100, true, 'Название');
        if ($id > 0) {
            CategoryRepository::renameCategory($id, $name);
        } else {
            CategoryRepository::createCategory($name);
        }
        return ['message' => 'Категория сохранена'];
    },

    'delete_category' => function () {
        CategoryRepository::deleteCategory(input_int('id', 1));
        return ['message' => 'Категория удалена'];
    },

    'save_subcategory' => function () {
        $id = (int)($_POST['id'] ?? 0);
        $name = input_str('name', 100, true, 'Название');
        if ($id > 0) {
            CategoryRepository::renameSubcategory($id, $name);
        } else {
            CategoryRepository::createSubcategory(input_int('category_id', 1, PHP_INT_MAX, 'Категория'), $name);
        }
        return ['message' => 'Подкатегория сохранена'];
    },

    'delete_subcategory' => function () {
        CategoryRepository::deleteSubcategory(input_int('id', 1));
        return ['message' => 'Подкатегория удалена'];
    },
]);
