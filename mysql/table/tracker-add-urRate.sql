ALTER TABLE tracker
    ADD `ur_rate_type` CHAR(1) NULL
        COMMENT 'A:Time-base, B:Engine-base, C:Distance-base',

    ADD `ur_rate_saturday` TINYINT NULL
        COMMENT 'include saturday',

    ADD `ur_rate_sunday` TINYINT NULL
        COMMENT 'include sunday',

    ADD `ur_rate_work_hour` TINYINT NULL
        COMMENT 'working hour/day',

    ADD `ur_rate_target_km` DECIMAL(8,2) NULL
        COMMENT 'target distance km/day for C:Distance-base'
;

ALTER TABLE tracker
    ADD `ur_rate_target_km` DECIMAL(8,2) NULL
        COMMENT 'target distance km/day for C:Distance-base';
