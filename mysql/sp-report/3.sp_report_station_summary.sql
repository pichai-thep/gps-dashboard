DELIMITER $$

DROP PROCEDURE IF EXISTS sp_report_station_summary $$
CREATE PROCEDURE sp_report_station_summary(
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_station_id INT,
    IN p_imeis TEXT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_offset INT DEFAULT 0;

    SET p_page = IFNULL(p_page, 1);
    SET p_per_page = IFNULL(p_per_page, 50);

    IF p_page < 1 THEN
        SET p_page = 1;
    END IF;

    IF p_per_page < 1 THEN
        SET p_per_page = 50;
    END IF;

    SET v_offset = (p_page - 1) * p_per_page;

    /* result set 1: summary + total */
    SELECT
        COUNT(*) AS total_rows,
        COUNT(DISTINCT s.imei) AS total_vehicle,
        COUNT(DISTINCT s.station_id) AS total_station,
        COALESCE(SUM(s.duration_s), 0) AS duration_s
    FROM gps_sum_station s
    INNER JOIN tracker t ON s.imei = t.imei
    WHERE s.data_date BETWEEN p_date_from AND p_date_to
      AND (
            p_station_id IS NULL
         OR p_station_id = 0
         OR s.station_id = p_station_id
      )
      AND (
            p_imeis IS NULL
         OR p_imeis = ''
         OR FIND_IN_SET(s.imei, p_imeis)
      );

    /* result set 2: pagination */
    SELECT
        p_page AS current_page,
        p_per_page AS per_page,
        v_offset AS offset;

    /* result set 3: rows */
    SELECT
        s.id,
        s.imei,
        t.plate_no,
        s.data_date,
        s.station_id,
        s.start_time,
        s.end_time,
        s.duration_s,
        s.updated_at
    FROM gps_sum_station s
    INNER JOIN tracker t ON s.imei = t.imei
    WHERE s.data_date BETWEEN p_date_from AND p_date_to
      AND (
            p_station_id IS NULL
         OR p_station_id = 0
         OR s.station_id = p_station_id
      )
      AND (
            p_imeis IS NULL
         OR p_imeis = ''
         OR FIND_IN_SET(s.imei, p_imeis)
      )
    ORDER BY
        s.data_date DESC,
        s.start_time DESC,
        s.imei ASC
    LIMIT p_per_page OFFSET v_offset;

END $$

DELIMITER ;