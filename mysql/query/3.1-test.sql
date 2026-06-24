CALL sp_sum_gps_report_daily('2026-05-23');

SHOW FULL COLUMNS FROM tracker;				-- utf8mb3_general_ci
SHOW FULL COLUMNS FROM customer_tracker;	-- utf8mb3_general_ci
SHOW FULL COLUMNS FROM gps_data_sum;		-- utf8mb3_general_ci