ALTER TABLE `gps_db`.`tracker` 
ADD COLUMN `ur_rate_type` CHAR(1) NULL DEFAULT NULL COMMENT 'A:Time-Base, B:Engine-Base' AFTER `std_rfid_timeout`,
ADD COLUMN `ur_rate_satsun` TINYINT NULL DEFAULT NULL COMMENT 'include saturday/sunday' AFTER `ur_rate_type`,
ADD COLUMN `ur_rate_work_hour` TINYINT NULL DEFAULT NULL COMMENT 'working hour/day' AFTER `ur_rate_satsun`,
ADD COLUMN `ur_rate_target_km` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'target distance km/day for C:Distance-base' AFTER `ur_rate_work_hour`;
