-- Run once in the existing foodcompass database after taking a backup.
-- Existing submissions default to "information unavailable"; never infer allergen safety.
USE foodcompass;
ALTER TABLE product_submissions
  ADD COLUMN allergen_status ENUM('unavailable','none_declared','declared') NOT NULL DEFAULT 'unavailable' AFTER description,
  ADD COLUMN allergens_json VARCHAR(500) NOT NULL DEFAULT '[]' AFTER allergen_status,
  ADD COLUMN vegetarian_claim ENUM('unknown','yes','no') NOT NULL DEFAULT 'unknown' AFTER allergens_json,
  ADD COLUMN vegan_claim ENUM('unknown','yes','no') NOT NULL DEFAULT 'unknown' AFTER vegetarian_claim,
  ADD COLUMN ingredients_photo VARCHAR(64) NULL AFTER vegan_claim,
  ADD COLUMN allergen_photo VARCHAR(64) NULL AFTER ingredients_photo,
  ADD COLUMN nutrition_photo VARCHAR(64) NULL AFTER allergen_photo;
