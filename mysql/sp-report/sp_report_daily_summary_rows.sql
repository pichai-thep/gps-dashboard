DROP PROCEDURE IF EXISTS sp_report_daily_summary_rows;

DELIMITER $$

CREATE PROCEDURE sp_report_daily_summary_rows(
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_imeis TEXT,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN

SELECT
    s.imei,
    t.plate_no,

    t.ur_rate_type,
    t.ur_rate_saturday,
    t.ur_rate_sunday,
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

        /* =========================================
           A = Time-base
           ========================================= */
        WHEN t.ur_rate_type = 'A' THEN

            CASE

                /* ไม่รวมวันเสาร์หรือวันอาทิตย์ตามการตั้งค่า */
                WHEN
                    (t.ur_rate_sunday = 0 AND DAYOFWEEK(s.data_date) = 1)
                        OR (t.ur_rate_saturday = 0 AND DAYOFWEEK(s.data_date) = 7)
                    THEN NULL

                ELSE
                    ROUND(
                            (
                                (s.run_time_s + s.idle_time_s)
                                    / NULLIF(
                                        (t.ur_rate_work_hour * 3600),
                                        0
                                      )
                                ) * 100,
                            2
                    )

                END

        /* =========================================
           B = Engine-base
           ========================================= */
        WHEN t.ur_rate_type = 'B' THEN

            ROUND(
                    (
                        (s.run_time_s + s.idle_time_s)
                            / NULLIF(
                                (
                                    s.run_time_s
                                        + s.idle_time_s
                                        + s.park_time_s
                                    ),
                                0
                              )
                        ) * 100,
                    2
            )

        /* =========================================
           C = Distance-base
           ========================================= */
        WHEN t.ur_rate_type = 'C' THEN

            ROUND(
                    (
                        (s.distance_m / 1000)
                            / NULLIF(
                                t.ur_rate_target_km,
                                0
                              )
                        ) * 100,
                    2
            )

        ELSE NULL

        END AS ur_rate,

    s.updated_at

FROM gps_data_sum s
         INNER JOIN tracker t
                    ON s.imei = t.imei

WHERE s.data_date BETWEEN p_date_from AND p_date_to

  AND (
    p_imeis IS NULL
        OR p_imeis = ''
        OR FIND_IN_SET(s.imei, p_imeis)
    )

ORDER BY
    s.data_date DESC,
    s.imei ASC

    LIMIT p_limit OFFSET p_offset;

END$$

DELIMITER ;
