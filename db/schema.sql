-- Database: anyprint_db
CREATE DATABASE IF NOT EXISTS anyprint_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anyprint_db;

-- Tabel Admin
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin (username: admin, password: admin123)
INSERT INTO admins (username, password, full_name) VALUES 
('admin', '$2y$10$2oC1AJKvNecIsKEwlclN9uGgvmLjeqtGAsPed0i1h451ZasPmmL8G', 'Administrator');

-- Tabel Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    file_name VARCHAR(255),
    total_pages INT DEFAULT 0,
    paper_size VARCHAR(20) DEFAULT 'A4',
    color_type VARCHAR(20) DEFAULT 'Black & White',
    copies INT DEFAULT 1,
    price_per_page DECIMAL(10, 2) DEFAULT 0.00,
    total_price DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    order_status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    customer_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_number (order_number),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Order Files (jika multiple files per order)
CREATE TABLE IF NOT EXISTS order_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size VARCHAR(20),
    file_pages INT DEFAULT 1,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Payment Logs
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(50),
    amount DECIMAL(10, 2),
    status VARCHAR(20),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;