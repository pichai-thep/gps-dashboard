DROP PROCEDURE IF EXISTS sp_report_daily_summary;

DELIMITER $$

CREATE PROCEDURE sp_report_daily_summary(
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_imeis TEXT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN

    DECLARE v_offset INT DEFAULT 0;

    SET p_page = IFNULL(p_page, 1);
    SET p_per_page = IFNULL(p_per_page, 100);

    IF p_page < 1 THEN
        SET p_page = 1;
    END IF;

    IF p_per_page < 1 THEN
        SET p_per_page = 100;
    END IF;

    SET v_offset = (p_page - 1) * p_per_page;

    /* result set 1: summary + total */
    SELECT
        COUNT(*) AS total_rows,
        COUNT(DISTINCT s.imei) AS total_vehicle,
        COALESCE(SUM(s.run_time_s), 0) AS run_time_s,
        COALESCE(SUM(s.idle_time_s), 0) AS idle_time_s,
        COALESCE(SUM(s.park_time_s), 0) AS park_time_s,
        COALESCE(SUM(s.distance_m), 0) AS distance_m
    FROM gps_sum_data s
    INNER JOIN tracker t ON s.imei = t.imei
    WHERE s.data_date BETWEEN p_date_from AND p_date_to
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
        s.imei,
        t.plate_no,

        t.ur_rate_type,
        t.ur_rate_satsun,
        t.ur_rate_work_hour,
        t.ur_rate_target_km,

        s.data_date,
        s.run_time_s,
        s.idle_time_s,
        s.park_time_s,
        s.distance_m,

        CASE
            WHEN t.ur_rate_type = 'A' THEN 'A:Time-base'
            WHEN t.ur_rate_type = 'B' THEN 'B:Engine-base'
            WHEN t.ur_rate_type = 'C' THEN 'C:Distance-base'
            ELSE '-'
        END AS ur_formula,

        CASE
            WHEN t.ur_rate_type = 'A' THEN
                CASE
                    WHEN t.ur_rate_satsun = 0
                         AND DAYOFWEEK(s.data_date) IN (1,7)
                    THEN NULL
                    ELSE ROUND(
                        (
                            (s.run_time_s + s.idle_time_s)
                            / NULLIF((t.ur_rate_work_hour * 3600), 0)
                        ) * 100,
                        2
                    )
                END

            WHEN t.ur_rate_type = 'B' THEN
                ROUND(
                    (
                        (s.run_time_s + s.idle_time_s)
                        / NULLIF(
                            (s.run_time_s + s.idle_time_s + s.park_time_s),
                            0
                        )
                    ) * 100,
                    2
                )

            WHEN t.ur_rate_type = 'C' THEN
                ROUND(
                    (
                        (s.distance_m / 1000)
                        / NULLIF(t.ur_rate_target_km, 0)
                    ) * 100,
                    2
                )

            ELSE NULL
        END AS ur_rate,

        s.updated_at

    FROM gps_sum_data s
    INNER JOIN tracker t ON s.imei = t.imei

    WHERE s.data_date BETWEEN p_date_from AND p_date_to
      AND (
            p_imeis IS NULL
         OR p_imeis = ''
         OR FIND_IN_SET(s.imei, p_imeis)
      )

    ORDER BY
        s.data_date DESC,
        s.imei ASC

    LIMIT p_per_page OFFSET v_offset;

END$$

DELIMITER ;