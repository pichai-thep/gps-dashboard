CALL sp_sum_gps_report_daily(curdate()-interval 1 day);
CALL sp_sum_gps_report_daily('2026-08-02');
CALL sp_sum_report_table(1,'2026-08-03',NULL);
CALL sp_sum_report_table(1,'2026-08-03',239);

CALL sp_run_summary_report_by_customer(
    239,          -- customer_id
    '2026-05-01', -- date_from
    '2026-05-31'  -- date_to
);

CALL sp_sum_station_daily(curdate()-interval 1 day);
CALL sp_sum_station_daily('2026-06-07');
CALL sp_sum_station_report_table(19, '2026-08-03');


SHOW FULL COLUMNS FROM tracker LIKE 'imei';
SHOW FULL COLUMNS FROM tracker LIKE 'report_table';
SHOW FULL COLUMNS FROM data_report LIKE 'box_imei';
SHOW FULL COLUMNS FROM customer_tracker LIKE 'tracker_imei';

SELECT
  TABLE_NAME,
  COLUMN_NAME,
  CHARACTER_SET_NAME,
  COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN (
    'imei',
    'box_imei',
    'tracker_imei',
    'report_table',
    'gps_status',
    'status'
  )
  AND COLLATION_NAME IS NOT NULL
ORDER BY COLLATION_NAME, TABLE_NAME, COLUMN_NAME;

ALTER TABLE station_data_13
  MODIFY imei varchar(20)
  CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL;
    
SHOW FULL COLUMNS FROM station_data_13 LIKE 'imei';
