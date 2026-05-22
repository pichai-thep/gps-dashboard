ALTER TABLE tracker
    ADD `ur_rate_type` CHAR(1) NULL
        COMMENT 'A:Time-base, B:Engine-base, C:Distance-base',

    ADD `ur_rate_satsun` TINYINT NULL
        COMMENT 'include saturday/sunday',

    ADD `ur_rate_work_hour` TINYINT NULL
        COMMENT 'working hour/day'
;