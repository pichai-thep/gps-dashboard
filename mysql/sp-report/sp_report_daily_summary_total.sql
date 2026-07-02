DROP PROCEDURE IF EXISTS sp_report_daily_summary_total;

DELIMITER $$

CREATE PROCEDURE sp_report_daily_summary_total(
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_imeis TEXT
)
BEGIN
SELECT
    COUNT(*) AS total_rows,
    COUNT(DISTINCT s.imei) AS total_vehicle,
    COALESCE(SUM(s.run_time_s), 0) AS run_time_s,
    COALESCE(SUM(s.idle_time_s), 0) AS idle_time_s,
    COALESCE(SUM(s.park_time_s), 0) AS park_time_s,
    COALESCE(SUM(s.distance_m), 0) AS distance_m
FROM gps_data_sum s
         INNER JOIN tracker t ON s.imei = t.imei
WHERE s.data_date BETWEEN p_date_from AND p_date_to
  AND (
    p_imeis IS NULL
        OR p_imeis = ''
        OR FIND_IN_SET(s.imei, p_imeis)
    );
END$$

DELIMITER ;