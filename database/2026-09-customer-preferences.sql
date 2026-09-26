-- Import once into the existing foodcompass database, after database/retail_schema.sql.
-- Stores customer selections; it does not classify products or assert medical suitability.
USE foodcompass;
CREATE TABLE customer_preferences (
  customer_id INT UNSIGNED NOT NULL PRIMARY KEY,
  avoided_allergens_json VARCHAR(500) NOT NULL DEFAULT '[]',
  dietary_preference ENUM('none','vegetarian','vegan') NOT NULL DEFAULT 'none',
  health_conditions_json VARCHAR(200) NOT NULL DEFAULT '[]',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_preferences_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
