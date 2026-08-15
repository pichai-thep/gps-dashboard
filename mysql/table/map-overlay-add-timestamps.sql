ALTER TABLE `pois`
  ADD COLUMN `created_at` datetime DEFAULT NULL AFTER `customer_customer_id`,
  ADD COLUMN `modified_at` datetime DEFAULT NULL AFTER `created_at`;

ALTER TABLE `forbidden_zones`
  ADD COLUMN `modified_at` datetime DEFAULT NULL AFTER `created_at`;

UPDATE `forbidden_zones`
SET `modified_at` = `created_at`
WHERE `modified_at` IS NULL;
