CREATE DATABASE IF NOT EXISTS bill_splitter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bill_splitter;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE bills (
    bill_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    restaurant_name VARCHAR(100),
    bill_date DATETIME,
    total_amount DECIMAL(10,2),
    image_path VARCHAR(255),
    share_token CHAR(64) NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE bill_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE
);

CREATE TABLE participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    user_id INT NULL,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    is_registered BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE item_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    participant_id INT NOT NULL,
    share_percentage DECIMAL(5,2) DEFAULT 100.00,
    FOREIGN KEY (item_id) REFERENCES bill_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(participant_id) ON DELETE CASCADE
);
