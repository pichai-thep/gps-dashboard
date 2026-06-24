DROP PROCEDURE IF EXISTS sp_report_daily_summary;

DELIMITER $$

CREATE PROCEDURE sp_report_daily_summary(
	IN p_login varchar(20),
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_imeis TEXT,
    IN p_page INT,
    IN p_per_page INT,
    IN p_sort_field VARCHAR(50),
    IN p_sort_order VARCHAR(4)
)
BEGIN
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_sort_field VARCHAR(50);
    DECLARE v_sort_order VARCHAR(4);

    SET p_page = IFNULL(p_page, 1);
    SET p_per_page = IFNULL(p_per_page, 100);

    IF p_page < 1 THEN SET p_page = 1; END IF;
    IF p_per_page < 1 THEN SET p_per_page = 100; END IF;

    SET v_offset = (p_page - 1) * p_per_page;

    SET v_sort_order = UPPER(IFNULL(p_sort_order, 'DESC'));

    IF v_sort_order NOT IN ('ASC', 'DESC') THEN
        SET v_sort_order = 'DESC';
    END IF;

    SET v_sort_field =
        CASE p_sort_field
            WHEN 'data_date' THEN 'data_date'
            WHEN 'imei' THEN 'imei'
            WHEN 'plate_no' THEN 'plate_no'
            WHEN 'run_time_s' THEN 'run_time_s'
            WHEN 'idle_time_s' THEN 'idle_time_s'
            WHEN 'park_time_s' THEN 'park_time_s'
            WHEN 'distance_m' THEN 'distance_m'
            WHEN 'ur_rate' THEN 'ur_rate'
            WHEN 'updated_at' THEN 'updated_at'
            ELSE 'data_date'
        END;

    DROP TEMPORARY TABLE IF EXISTS tmp_daily_summary_rows;

    CREATE TEMPORARY TABLE tmp_daily_summary_rows AS
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
                        ((s.run_time_s + s.idle_time_s)
                        / NULLIF((t.ur_rate_work_hour * 3600), 0)) * 100,
                        2
                    )
                END

            WHEN t.ur_rate_type = 'B' THEN
                ROUND(
                    ((s.run_time_s + s.idle_time_s)
                    / NULLIF((s.run_time_s + s.idle_time_s + s.park_time_s), 0)) * 100,
                    2
                )

            WHEN t.ur_rate_type = 'C' THEN
                ROUND(
                    ((s.distance_m / 1000)
                    / NULLIF(t.ur_rate_target_km, 0)) * 100,
                    2
                )

            ELSE NULL
        END AS ur_rate,

        s.updated_at

    FROM gps_sum_data s
		INNER JOIN tracker t ON s.imei = t.imei
		inner join customer_tracker ct on ct.tracker_imei=t.imei
		inner join customer c on c.customer_id=ct.customer_customer_id 
		inner join customer_user cu on cu.customer_customer_id=c.customer_id
		inner join user_tracker ut on t.imei=ut.tracker_imei
		inner join user u on ut.user_user_id=u.user_id    
    WHERE s.data_date BETWEEN p_date_from AND p_date_to
		AND trim(u.login)=trim(p_login)
		AND (
			p_imeis IS NULL
		 OR p_imeis = ''
		 OR FIND_IN_SET(s.imei, p_imeis)
		);

    /* result set 1: summary */
    SELECT
        COUNT(*) AS total_rows,
        COUNT(DISTINCT imei) AS total_vehicle,
        COALESCE(SUM(run_time_s), 0) AS run_time_s,
        COALESCE(SUM(idle_time_s), 0) AS idle_time_s,
        COALESCE(SUM(park_time_s), 0) AS park_time_s,
        COALESCE(SUM(distance_m), 0) AS distance_m,
        ROUND(AVG(ur_rate), 2) AS ur_rate_avg
    FROM tmp_daily_summary_rows;

    /* result set 2: pagination */
    SELECT
        p_page AS current_page,
        p_per_page AS per_page,
        v_offset AS offset,
        COUNT(*) AS total_rows,
        CEIL(COUNT(*) / p_per_page) AS total_pages
    FROM tmp_daily_summary_rows;

    /* result set 3: rows */
    SET @sql = CONCAT(
        'SELECT * FROM tmp_daily_summary_rows ',
        'ORDER BY ', v_sort_field, ' ', v_sort_order, ', data_date DESC, imei ASC ',
        'LIMIT ', p_per_page, ' OFFSET ', v_offset
    );

    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    DROP TEMPORARY TABLE IF EXISTS tmp_daily_summary_rows;
END$$

DELIMITER ;