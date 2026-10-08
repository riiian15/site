CREATE DATABASE IF NOT EXISTS meduzi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meduzi_db;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    has_subcategories TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    product_condition ENUM('new', 'used') NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE product_specs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    spec_name VARCHAR(100) NOT NULL,
    spec_value TEXT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    comment TEXT,
    order_type ENUM('purchase', 'rental', 'repair', 'consult') NOT NULL,
    status ENUM('new', 'processing', 'completed', 'cancelled') DEFAULT 'new',
    total_amount DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    item_type ENUM('purchase', 'rental', 'repair') NOT NULL,
    quantity INT DEFAULT 1,
    price DECIMAL(10, 2),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    rental_type ENUM('short_term', 'long_term') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    deposit DECIMAL(10, 2),
    status ENUM('pending', 'active', 'returned', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

INSERT INTO categories (name, description, has_subcategories) VALUES
('УЗИ-аппараты', 'Стационарные и портативные ультразвуковые системы', 1),
('Датчики', 'Конвексные, линейные, секторные, эндоскопические датчики', 1),
('Запасные части', 'Платы, блоки питания, мониторы, комплектующие', 0),
('Прочее оборудование', 'Гели, кабели, тележки, стабилизаторы', 0);

INSERT INTO users (username, password_hash, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uQE7wO6K', 'admin@meduzi.ru', 'admin');

INSERT INTO products (name, brand, year, price, product_condition, category_id, description) VALUES
('GE Voluson E10', 'GE', 2024, 4500000, 'new', 1, 'Флагманская система экспертного класса для акушерства и гинекологии.'),
('Philips EPIQ Elite', 'Philips', 2023, 3800000, 'new', 1, 'Премиальная система с технологией nSIGHT для кардиологии.'),
('Samsung HS50', 'Samsung', 2021, 1800000, 'used', 1, 'Универсальная система среднего класса. Полностью обслужена.');

INSERT INTO product_specs (product_id, spec_name, spec_value) VALUES
(1, 'Тип', 'Стационарный'),
(1, 'Класс', 'Экспертный'),
(1, 'Режимы', '2D, 3D, 4D, CW, PW, Color Doppler'),
(1, 'Монитор', '23" LED'),
(1, 'Каналов', '256'),
(1, 'Вес', '115 кг');