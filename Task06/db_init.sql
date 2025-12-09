-- ===============================================
--  ИНИЦИАЛИЗАЦИЯ БАЗЫ ДАННЫХ ДЛЯ МОЙКИ АВТО
-- ===============================================

PRAGMA foreign_keys = ON;

-- ===============================================
--  ТАБЛИЦА СОТРУДНИКОВ (мастера, персонал)
-- ===============================================
--  Сохраняем и активных, и уволенных сотрудников,
--  чтобы история выполненных работ не терялась.
CREATE TABLE IF NOT EXISTS employee (
    employee_id INTEGER PRIMARY KEY,                 -- идентификатор сотрудника
    first_name TEXT NOT NULL,                        -- имя
    last_name TEXT NOT NULL,                         -- фамилия
    role TEXT NOT NULL DEFAULT 'master',             -- роль: мастер / админ / уборщик
    phone TEXT,                                      -- телефон
    hire_date TEXT NOT NULL DEFAULT (DATE('now')),   -- дата найма
    employment_end_date TEXT,                        -- дата увольнения (NULL если работает)
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)), -- активен ли
    revenue_percent REAL NOT NULL DEFAULT 40.0 CHECK (revenue_percent BETWEEN 0 AND 100),
    notes TEXT                                       -- примечания
);

-- ===============================================
--  ТАБЛИЦА БОКСОВ (постов мойки)
-- ===============================================
CREATE TABLE IF NOT EXISTS box (
    box_id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    is_available INTEGER NOT NULL DEFAULT 1 CHECK (is_available IN (0,1))   -- доступен ли бокс
);

-- ===============================================
--  ТАБЛИЦА КАТЕГОРИЙ УСЛУГ
-- ===============================================
CREATE TABLE IF NOT EXISTS service_category (
    category_id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT
);

-- ===============================================
--  ТАБЛИЦА УСЛУГ
-- ===============================================
CREATE TABLE IF NOT EXISTS service (
    service_id INTEGER PRIMARY KEY,
    category_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    duration_minutes INTEGER NOT NULL CHECK (duration_minutes > 0),  -- длительность услуги
    price_cents INTEGER NOT NULL CHECK (price_cents >= 0),           -- цена в копейках
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    FOREIGN KEY (category_id) REFERENCES service_category(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ===============================================
--  ТАБЛИЦА КЛИЕНТОВ
-- ===============================================
CREATE TABLE IF NOT EXISTS customer (
    customer_id INTEGER PRIMARY KEY,
    full_name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    notes TEXT
);

-- ===============================================
--  ТАБЛИЦА ЗАПИСЕЙ (предварительная бронь)
-- ===============================================
--  Статусы: scheduled — запланировано
--            canceled — отменено
--            completed — выполнено
--            no-show — клиент не пришёл
CREATE TABLE IF NOT EXISTS booking (
    booking_id INTEGER PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    box_id INTEGER,         -- может быть NULL, если бокс ещё не назначен
    master_id INTEGER,      -- предпочтительный мастер
    scheduled_start TEXT NOT NULL,
    scheduled_end TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'scheduled'
        CHECK (status IN ('scheduled','canceled','completed','no-show')),
    created_at TEXT NOT NULL DEFAULT (DATETIME('now')),
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (service_id) REFERENCES service(service_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (box_id) REFERENCES box(box_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (master_id) REFERENCES employee(employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ===============================================
--  ТАБЛИЦА ФАКТИЧЕСКИ ВЫПОЛНЕННЫХ РАБОТ
-- ===============================================
CREATE TABLE IF NOT EXISTS work_record (
    work_id INTEGER PRIMARY KEY,
    booking_id INTEGER,           -- может быть NULL (клиент без записи)
    service_id INTEGER NOT NULL,
    master_id INTEGER NOT NULL,
    box_id INTEGER NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT,
    price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
    created_at TEXT NOT NULL DEFAULT (DATETIME('now')),
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES booking(booking_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (service_id) REFERENCES service(service_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (master_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (box_id) REFERENCES box(box_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ===============================================
--  ТАБЛИЦА ПЛАТЕЖЕЙ
-- ===============================================
CREATE TABLE IF NOT EXISTS payment (
    payment_id INTEGER PRIMARY KEY,
    work_id INTEGER NOT NULL,
    amount_cents INTEGER NOT NULL CHECK (amount_cents >= 0),
    paid_at TEXT NOT NULL DEFAULT (DATETIME('now')),
    method TEXT, -- наличные / карта / перевод
    FOREIGN KEY (work_id) REFERENCES work_record(work_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ===============================================
--  ИНДЕКСЫ ДЛЯ УСКОРЕНИЯ ЗАПРОСОВ
-- ===============================================
CREATE INDEX IF NOT EXISTS idx_work_master_time ON work_record(master_id, start_time);
CREATE INDEX IF NOT EXISTS idx_booking_time ON booking(scheduled_start);

-- ===============================================
--  ТЕСТОВЫЕ ДАННЫЕ
-- ===============================================
BEGIN TRANSACTION;

-- Сотрудники
INSERT INTO employee VALUES
 (1,'Иван','Петров','master','+7-900-111-22-33','2020-03-01',NULL,1,50.0,'Старший мастер'),
 (2,'Анна','Иванова','master','+7-900-222-33-44','2021-06-15',NULL,1,45.0,NULL),
 (3,'Пётр','Сидоров','master','+7-900-333-44-55','2019-11-08','2024-12-01',0,40.0,'Уволен');

-- Боксы
INSERT INTO box VALUES
 (1,'Бокс 1','У входа',1),
 (2,'Бокс 2','Средний',1),
 (3,'Бокс 3','Дальний',1);

-- Категории
INSERT INTO service_category VALUES
 (1,'Легковые автомобили','Компактные машины'),
 (2,'Кроссоверы и внедорожники','Крупные авто');

-- Услуги
INSERT INTO service VALUES
 (1,1,'Внешняя мойка',20,5000,1),
 (2,1,'Уборка салона',40,8000,1),
 (3,2,'Полная мойка SUV',35,9000,1);

-- Клиенты
INSERT INTO customer VALUES
 (1,'Сергей К.','+7-901-111-11-11','sergey@example.com',NULL),
 (2,'Ольга М.','+7-902-222-22-22',NULL,'VIP-клиент');

-- Записи
INSERT INTO booking VALUES
 (1,1,1,1,1,'2025-12-05 09:00','2025-12-05 09:20','completed','2025-11-30 10:00',NULL),
 (2,2,3,2,2,'2025-12-06 10:00','2025-12-06 10:35','scheduled','2025-12-01 12:00',NULL);

-- Выполненные работы
INSERT INTO work_record VALUES
 (1,1,1,1,1,'2025-12-05 09:00','2025-12-05 09:20',5000,'2025-12-05 09:25','Всё отлично'),
 (2,NULL,2,2,2,'2025-12-04 11:00','2025-12-04 11:45',8000,'2025-12-04 11:45','Клиент без записи');

-- Платежи
INSERT INTO payment VALUES
 (1,1,5000,'2025-12-05 09:25','наличные'),
 (2,2,8000,'2025-12-04 11:50','карта');

COMMIT;

-- ================================================
--  ПРЕДСТАВЛЕНИЕ: ВЫРУЧКА ПО МАСТЕРАМ
-- ================================================
CREATE VIEW IF NOT EXISTS master_revenue AS
SELECT
    e.employee_id,
    e.first_name || ' ' || e.last_name AS master_name,
    SUM(w.price_cents) AS total_revenue_cents,
    e.revenue_percent
FROM employee e
LEFT JOIN work_record w ON e.employee_id = w.master_id
GROUP BY e.employee_id;