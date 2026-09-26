-- Run once in the existing foodcompass database after taking a backup.
-- Keep package photos on submission versions so edits wait for approval.
USE foodcompass;
ALTER TABLE product_submissions
  ADD COLUMN product_photo VARCHAR(64) NULL AFTER nutrition_photo;
