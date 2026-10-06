-- Run on each GPS database (the singular `user` table), before deploying the API.
-- Customer columns are retained for the legacy website.
ALTER TABLE `user`
    ADD `show_input1` tinyint(1) DEFAULT NULL,
    ADD `show_input2` tinyint(1) DEFAULT NULL,
    ADD `show_temp` tinyint DEFAULT NULL,
    ADD `show_fuel` tinyint DEFAULT NULL,
    ADD `enable_engine_cut` tinyint DEFAULT NULL,
    ADD `engine_cut_pwd` varchar(6) DEFAULT NULL;

-- NULL visibility flags are disabled in the new website.
-- Run user-backfill-display-options.sql to copy existing customer options.
-- Users with conflicting customer options require an explicit source choice.

-- ALTER TABLE `user`
--     ADD `show_fuel` tinyint DEFAULT NULL;