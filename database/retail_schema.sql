-- Add only after the existing FoodCompass users/products/product_submissions schema is present.
-- No demo users or fixed IDs are inserted. Back up foodcompass before importing once.
USE foodcompass;
CREATE TABLE branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  retailer_id INT UNSIGNED NOT NULL,
  branch_name VARCHAR(120) NOT NULL,
  area VARCHAR(120) NOT NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_area (area), KEY idx_retailer (retailer_id),
  CONSTRAINT fk_branch_retailer FOREIGN KEY (retailer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE branch_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NULL,
  stock_status ENUM('available','out_of_stock','unknown') NOT NULL DEFAULT 'unknown',
  last_reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_branch_product (branch_id, product_id),
  CONSTRAINT fk_bp_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_bp_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE saved_lists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  list_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_customer (customer_id),
  CONSTRAINT fk_list_customer FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE saved_list_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  list_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_list_product (list_id, product_id),
  CONSTRAINT fk_item_list FOREIGN KEY (list_id) REFERENCES saved_lists(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE availability_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  list_id INT UNSIGNED NULL,
  branch_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  list_name VARCHAR(120) NOT NULL,
  status ENUM('pending','responded') NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_branch (branch_id), KEY idx_customer (customer_id),
  CONSTRAINT fk_req_list FOREIGN KEY (list_id) REFERENCES saved_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_req_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_req_customer FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE request_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  requested_quantity INT UNSIGNED NOT NULL,
  response ENUM('available','unavailable','partial','unknown') NOT NULL DEFAULT 'unknown',
  note VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uq_request_product (request_id, product_id),
  CONSTRAINT fk_ri_request FOREIGN KEY (request_id) REFERENCES availability_requests(id),
  CONSTRAINT fk_ri_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
