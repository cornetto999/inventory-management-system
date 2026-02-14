-- inventory_system.sql (MySQL 8+)
-- Converted from Supabase migrations (Postgres) to MySQL for XAMPP.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP DATABASE IF EXISTS inventory_system;
CREATE DATABASE inventory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_system;

-- Users (replaces Supabase auth.users + profiles + user_roles)
CREATE TABLE users (
  id CHAR(36) NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE categories (
  id CHAR(36) NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id CHAR(36) NOT NULL,
  name VARCHAR(200) NOT NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_suppliers_name (name)
) ENGINE=InnoDB;

CREATE TABLE products (
  id CHAR(36) NOT NULL,
  sku VARCHAR(80) NOT NULL,
  name VARCHAR(200) NOT NULL,
  category_id CHAR(36) NOT NULL,
  supplier_id CHAR(36) NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  stock INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_name (name),
  KEY idx_products_category (category_id),
  KEY idx_products_supplier (supplier_id),
  KEY idx_products_status (status),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE stock_in (
  id CHAR(36) NOT NULL,
  product_id CHAR(36) NOT NULL,
  qty INT NOT NULL,
  cost_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  supplier_id CHAR(36) NULL,
  remarks VARCHAR(255) NULL,
  created_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stockin_product (product_id),
  KEY idx_stockin_created_at (created_at),
  KEY idx_stockin_supplier (supplier_id),
  KEY idx_stockin_created_by (created_by),
  CONSTRAINT fk_stockin_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_stockin_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_stockin_user FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE stock_out (
  id CHAR(36) NOT NULL,
  product_id CHAR(36) NOT NULL,
  qty INT NOT NULL,
  remarks VARCHAR(255) NULL,
  customer VARCHAR(200) NULL,
  created_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stockout_product (product_id),
  KEY idx_stockout_created_at (created_at),
  KEY idx_stockout_created_by (created_by),
  CONSTRAINT fk_stockout_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_stockout_user FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id CHAR(36) NOT NULL,
  product_id CHAR(36) NOT NULL,
  movement_type ENUM('IN','OUT','ADJUST') NOT NULL,
  qty INT NOT NULL,
  prev_stock INT NOT NULL,
  new_stock INT NOT NULL,
  ref_table VARCHAR(50) NULL,
  ref_id CHAR(36) NULL,
  user_id CHAR(36) NOT NULL,
  remarks VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movements_product (product_id),
  KEY idx_movements_type (movement_type),
  KEY idx_movements_created_at (created_at),
  KEY idx_movements_user (user_id),
  KEY idx_movements_ref (ref_table, ref_id),
  CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Seed data
INSERT INTO categories (id, name) VALUES
  ('11111111-1111-1111-1111-111111111111', 'Electronics'),
  ('22222222-2222-2222-2222-222222222222', 'Furniture'),
  ('33333333-3333-3333-3333-333333333333', 'Office Supplies'),
  ('44444444-4444-4444-4444-444444444444', 'Raw Materials'),
  ('55555555-5555-5555-5555-555555555555', 'Packaging');

INSERT INTO suppliers (id, name, phone, address) VALUES
  ('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'TechParts Inc.', '+1-555-0101', '123 Industrial Ave, Chicago IL'),
  ('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Global Supply Co.', '+1-555-0202', '456 Commerce St, New York NY'),
  ('cccccccc-cccc-cccc-cccc-cccccccccccc', 'PackRight Ltd.', '+1-555-0303', '789 Warehouse Blvd, Dallas TX');

-- Default accounts (change after first login)
-- Admin: admin@example.com / Admin@12345
-- Staff: staff@example.com / Staff@12345
-- Password hashes generated using PHP password_hash(...)
INSERT INTO users (id, name, email, password_hash, role, status) VALUES
  ('99999999-9999-9999-9999-999999999999', 'System Admin', 'admin@example.com', '$2y$10$TZmm2XIJbcHakB20kbuDfO18haTNZjXCyLrVDkJxGcl791EItBi0O', 'admin', 'active'),
  ('88888888-8888-8888-8888-888888888888', 'Staff User',  'staff@example.com', '$2y$10$2J0xHkde7kXLCa1TqkMv8uebTcVth7dXRDZ8iwB/efn1liLOf4r5e', 'staff', 'active');
