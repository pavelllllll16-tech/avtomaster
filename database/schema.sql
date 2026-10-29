-- =====================================================================
--  АвтоМастер — система учёта автозапчастей
--  Схема базы данных + демонстрационные данные
--  Импорт: phpMyAdmin → Импорт → выбрать этот файл
-- =====================================================================

CREATE DATABASE IF NOT EXISTS auto_parts_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auto_parts_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS supplier_returns, supplier_parts, suppliers, order_items, orders,
                     payment_methods, parts, subcategories, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE subcategories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_subcat (category_id, name),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Запчасти (складской остаток хранится в quantity)
CREATE TABLE parts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    subcategory_id INT NOT NULL,
    code           VARCHAR(50)  NOT NULL UNIQUE,
    name           VARCHAR(200) NOT NULL,
    quantity       INT NOT NULL DEFAULT 0,
    min_quantity   INT NOT NULL DEFAULT 2,
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    selling_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT chk_parts_qty CHECK (quantity >= 0),
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payment_methods (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Заказы. status: 0 — открыт, 1 — выполнен, 2 — отменён
CREATE TABLE orders (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    customer_name     VARCHAR(150) NULL,
    total_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount          DECIMAL(5,2)  NOT NULL DEFAULT 0,
    final_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes             TEXT NULL,
    payment_method_id INT NULL,
    status            TINYINT NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_status_date (status, created_at),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL,
    part_id        INT NOT NULL,
    quantity       INT NOT NULL,
    price          DECIMAL(10,2) NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0,  -- закупочная цена на момент продажи (для расчёта прибыли)
    discount       DECIMAL(5,2)  NOT NULL DEFAULT 0,
    total_price    DECIMAL(12,2) NOT NULL,
    UNIQUE KEY uq_order_part (order_id, part_id),
    CONSTRAINT chk_oi_qty CHECK (quantity > 0),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id)  REFERENCES parts(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150) NOT NULL UNIQUE,
    contact_info VARCHAR(255) NULL
) ENGINE=InnoDB;

-- Позиции поставок. Одна накладная = несколько строк с общим invoice_number
CREATE TABLE supplier_parts (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id        INT NOT NULL,
    part_id            INT NOT NULL,
    quantity           INT NOT NULL,
    price              DECIMAL(10,2) NOT NULL,
    payment_delay_days INT NOT NULL DEFAULT 0,
    payment_method_id  INT NULL,
    supply_date        DATE NOT NULL,
    invoice_number     VARCHAR(50) NOT NULL,
    payment_status     ENUM('paid','unpaid') NOT NULL DEFAULT 'unpaid',
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_invoice (invoice_number),
    CONSTRAINT chk_sp_qty CHECK (quantity > 0),
    FOREIGN KEY (supplier_id)       REFERENCES suppliers(id)       ON DELETE RESTRICT,
    FOREIGN KEY (part_id)           REFERENCES parts(id)           ON DELETE RESTRICT,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE supplier_returns (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    supply_id   INT NOT NULL,
    return_date DATE NOT NULL,
    quantity    INT NOT NULL,
    reason      VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_sr_qty CHECK (quantity > 0),
    FOREIGN KEY (supply_id) REFERENCES supplier_parts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  Демонстрационные данные
-- =====================================================================

INSERT INTO payment_methods (name) VALUES ('Наличные'), ('Банковская карта'), ('Безналичный расчёт');

INSERT INTO categories (name) VALUES ('Двигатель'), ('Тормозная система'), ('Подвеска'), ('Электрика'), ('Масла и жидкости');

INSERT INTO subcategories (category_id, name) VALUES
 (1, 'Фильтры'), (1, 'Ремни и ролики'), (1, 'Свечи зажигания'),
 (2, 'Тормозные колодки'), (2, 'Тормозные диски'),
 (3, 'Амортизаторы'), (3, 'Рычаги и шаровые'),
 (4, 'Аккумуляторы'), (4, 'Лампы'),
 (5, 'Моторные масла'), (5, 'Антифризы');

INSERT INTO parts (subcategory_id, code, name, quantity, min_quantity, purchase_price, selling_price) VALUES
 (1,  'MANN-W712',    'Масляный фильтр MANN W712/75',         22, 5, 420.00,  650.00),
 (1,  'BOSCH-F026',   'Воздушный фильтр Bosch F026400',       15, 4, 610.00,  890.00),
 (1,  'MANN-CU2939',  'Салонный фильтр MANN CU 2939',          9, 4, 540.00,  820.00),
 (2,  'GATES-5462',   'Ремень ГРМ Gates 5462XS',               5, 2, 1850.00, 2690.00),
 (2,  'INA-531',      'Ролик натяжной INA 531 0507 10',        1, 2, 1450.00, 2150.00),
 (3,  'NGK-BKR6E',    'Свеча зажигания NGK BKR6E',            48, 12, 210.00, 340.00),
 (4,  'TRW-GDB1550',  'Колодки передние TRW GDB1550',          9, 3, 1900.00, 2750.00),
 (4,  'BREMBO-P85',   'Колодки задние Brembo P 85 020',        6, 3, 1650.00, 2390.00),
 (5,  'BREMBO-09A',   'Диск тормозной Brembo 09.A820',         4, 2, 3200.00, 4490.00),
 (6,  'KYB-334837',   'Амортизатор KYB Excel-G 334837',        3, 2, 4100.00, 5790.00),
 (7,  'LEMF-2589',    'Рычаг подвески Lemförder 25890',        2, 2, 3600.00, 4990.00),
 (8,  'VARTA-E11',    'АКБ Varta Blue Dynamic E11 74Ah',       2, 1, 8900.00, 11490.00),
 (9,  'OSRAM-H7',     'Лампа Osram H7 Night Breaker',         34, 10, 690.00, 1090.00),
 (9,  'PHIL-H4',      'Лампа Philips H4 Vision',              20, 8, 380.00,  590.00),
 (10, 'SHELL-5W40',   'Масло Shell Helix HX8 5W-40 4л',       14, 5, 2350.00, 3190.00),
 (10, 'MOBIL-5W30',   'Масло Mobil Super 3000 5W-30 4л',       0, 4, 2550.00, 3490.00),
 (11, 'FELIX-G12',    'Антифриз Felix Carbox G12+ 5кг',       11, 4, 690.00,  990.00);

INSERT INTO suppliers (name, contact_info) VALUES
 ('ООО «АвтоТрейд»',  '+7 (900) 111-22-33, info@autotrade.example'),
 ('ИП Смирнов С.В.',  '+7 (900) 444-55-66'),
 ('ООО «ЕвроДеталь»', '+7 (900) 777-88-99, sale@eurodetal.example');

INSERT INTO supplier_parts (supplier_id, part_id, quantity, price, payment_delay_days, payment_method_id, supply_date, invoice_number, payment_status) VALUES
 (1, 1,  20, 420.00,  14, 3, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'ПН-0001', 'paid'),
 (1, 7,  10, 1900.00, 14, 3, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'ПН-0001', 'paid'),
 (1, 15, 12, 2350.00, 14, 3, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'ПН-0001', 'paid'),
 (2, 13, 30, 690.00,  7,  1, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'ПН-0002', 'unpaid'),
 (2, 6,  40, 210.00,  7,  1, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'ПН-0002', 'unpaid'),
 (3, 9,  4,  3200.00, 30, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'ПН-0003', 'unpaid'),
 (3, 10, 4,  4100.00, 30, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'ПН-0003', 'unpaid');

INSERT INTO supplier_returns (supply_id, return_date, quantity, reason) VALUES
 (4, DATE_SUB(CURDATE(), INTERVAL 18 DAY), 2, 'Брак упаковки');

-- Заказы за последний месяц (для графиков на главной)
INSERT INTO orders (customer_name, total_amount, discount, final_amount, payment_method_id, status, created_at) VALUES
 ('Иванов И.И.',    4050.00, 0, 4050.00, 2, 1, DATE_SUB(NOW(), INTERVAL 27 DAY)),
 ('Сидоров П.А.',   5380.00, 5, 5111.00, 1, 1, DATE_SUB(NOW(), INTERVAL 22 DAY)),
 (NULL,             1360.00, 0, 1360.00, 1, 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
 ('Кузнецова А.В.', 11490.00,0, 11490.00,2, 1, DATE_SUB(NOW(), INTERVAL 13 DAY)),
 ('Морозов Д.С.',   7650.00, 0, 7650.00, 2, 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),
 (NULL,             2180.00, 0, 2180.00, 1, 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
 ('Волков Е.Н.',    6380.00, 10,5742.00, 2, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
 ('Новиков Р.Р.',   3190.00, 0, 3190.00, 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
 ('Лебедев К.О.',   2750.00, 0, 2750.00, NULL, 0, NOW());

INSERT INTO order_items (order_id, part_id, quantity, price, purchase_price, discount, total_price) VALUES
 (1, 1,  2, 650.00,   420.00,  0, 1300.00),
 (1, 7,  1, 2750.00,  1900.00, 0, 2750.00),
 (2, 4,  2, 2690.00,  1850.00, 0, 5380.00),
 (3, 6,  4, 340.00,   210.00,  0, 1360.00),
 (4, 12, 1, 11490.00, 8900.00, 0, 11490.00),
 (5, 15, 2, 3190.00,  2350.00, 0, 6380.00),
 (5, 6,  2, 340.00,   210.00,  0, 680.00),
 (5, 14, 1, 590.00,   380.00,  0, 590.00),
 (6, 13, 2, 1090.00,  690.00,  0, 2180.00),
 (7, 15, 2, 3190.00,  2350.00, 0, 6380.00),
 (8, 15, 1, 3190.00,  2350.00, 0, 3190.00),
 (9, 7,  1, 2750.00,  1900.00, 0, 2750.00);
