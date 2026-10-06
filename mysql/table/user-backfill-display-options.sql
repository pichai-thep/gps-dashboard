-- Run on each GPS database AFTER user-display-options.sql.
-- Copies all six options into `user`; existing user options are overwritten.
-- Fuel visibility maps customer.enable_fuel_chk to user.show_fuel.
-- `customer` remains unchanged. Run once during migration, before configuring
-- independent user options. Back up the database before running this script.
-- Users with conflicting customer options are skipped and listed below.

DROP TEMPORARY TABLE IF EXISTS user_customer_options;
CREATE TEMPORARY TABLE user_customer_options AS
SELECT
    cu.user_user_id AS user_id,
    MIN(c.show_input1) AS show_input1,
    MIN(c.show_input2) AS show_input2,
    MIN(c.show_temp) AS show_temp,
    MIN(c.enable_fuel_chk) AS show_fuel,
    MIN(c.enable_engine_cut) AS enable_engine_cut,
    MIN(c.engine_cut_pwd) AS engine_cut_pwd,
    COUNT(DISTINCT c.customer_id) AS customer_count,
    (
        COUNT(DISTINCT c.show_input1) + MAX(c.show_input1 IS NULL) = 1
        AND COUNT(DISTINCT c.show_input2) + MAX(c.show_input2 IS NULL) = 1
        AND COUNT(DISTINCT c.enable_fuel_chk) + MAX(c.enable_fuel_chk IS NULL) = 1
        AND COUNT(DISTINCT c.show_temp) + MAX(c.show_temp IS NULL) = 1
        AND COUNT(DISTINCT c.enable_engine_cut) + MAX(c.enable_engine_cut IS NULL) = 1
        AND COUNT(DISTINCT BINARY c.engine_cut_pwd) + MAX(c.engine_cut_pwd IS NULL) = 1
    ) AS options_agree
FROM customer_user AS cu
JOIN customer AS c ON c.customer_id = cu.customer_customer_id
GROUP BY cu.user_user_id;

-- Review users that will be copied (passwords are intentionally not displayed).
SELECT u.user_id, u.login, o.customer_count,
       o.show_input1, o.show_input2, o.show_temp, o.show_fuel, o.enable_engine_cut
FROM `user` AS u
JOIN user_customer_options AS o ON o.user_id = u.user_id
WHERE o.options_agree = 1
ORDER BY u.user_id;

-- Conflicting users: choose the source customer manually for these users.
SELECT u.user_id, u.login, o.customer_count
FROM `user` AS u
JOIN user_customer_options AS o ON o.user_id = u.user_id
WHERE o.options_agree = 0
ORDER BY u.user_id;

-- Users without a valid customer relationship remain unchanged.
SELECT u.user_id, u.login
FROM `user` AS u
LEFT JOIN user_customer_options AS o ON o.user_id = u.user_id
WHERE o.user_id IS NULL
ORDER BY u.user_id;

START TRANSACTION;

UPDATE `user` AS u
JOIN user_customer_options AS o ON o.user_id = u.user_id
SET u.show_input1 = o.show_input1,
    u.show_input2 = o.show_input2,
    u.show_temp = o.show_temp,
    u.show_fuel = o.show_fuel,
    u.enable_engine_cut = o.enable_engine_cut,
    u.engine_cut_pwd = o.engine_cut_pwd
WHERE o.options_agree = 1;

SELECT ROW_COUNT() AS changed_users;

COMMIT;

DROP TEMPORARY TABLE user_customer_options;
