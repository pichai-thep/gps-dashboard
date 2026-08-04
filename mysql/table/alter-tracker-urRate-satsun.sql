ALTER TABLE `tracker` 
	DROP COLUMN `ur_rate_satsun`,
	
    ADD COLUMN `ur_rate_saturday` TINYINT NULL DEFAULT NULL COMMENT 'include saturday' AFTER `ur_rate_type`,
    ADD COLUMN `ur_rate_sunday` TINYINT NULL DEFAULT NULL COMMENT 'include sunday' AFTER `ur_rate_saturday`
    
;
