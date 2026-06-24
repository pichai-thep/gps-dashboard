DELIMITER $$

DROP PROCEDURE IF EXISTS sp_report_status_summary $$

CREATE PROCEDURE sp_report_status_summary(
	IN p_login varchar(20),
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_status VARCHAR(20),
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
    SET p_per_page = IFNULL(p_per_page, 50);

    IF p_page < 1 THEN
        SET p_page = 1;
    END IF;

    IF p_per_page < 1 THEN
        SET p_per_page = 50;
    END IF;

    SET v_offset = (p_page - 1) * p_per_page;

    SET v_sort_order = UPPER(IFNULL(p_sort_order, 'DESC'));

    IF v_sort_order NOT IN ('ASC', 'DESC') THEN
        SET v_sort_order = 'DESC';
    END IF;

    SET v_sort_field =
        CASE p_sort_field
            WHEN 'id' THEN 'id'
            WHEN 'imei' THEN 'imei'
            WHEN 'plate_no' THEN 'plate_no'
            WHEN 'data_date' THEN 'data_date'
            WHEN 'gps_status' THEN 'gps_status'
            WHEN 'start_time' THEN 'start_time'
            WHEN 'end_time' THEN 'end_time'
            WHEN 'duration_s' THEN 'duration_s'
            WHEN 'updated_at' THEN 'updated_at'
            ELSE 'data_date'
        END;

    DROP TEMPORARY TABLE IF EXISTS tmp_status_summary_rows;

    CREATE TEMPORARY TABLE tmp_status_summary_rows AS
    SELECT
        s.id,
        s.imei,
        t.plate_no,
        s.data_date,
        s.gps_status,
        s.start_time,
        s.end_time,
        s.duration_s,
        s.updated_at
    FROM gps_sum_status s
    INNER JOIN tracker t ON s.imei = t.imei
		inner join customer_tracker ct on ct.tracker_imei=t.imei
		inner join customer c on c.customer_id=ct.customer_customer_id 
		inner join customer_user cu on cu.customer_customer_id=c.customer_id
		inner join user_tracker ut on t.imei=ut.tracker_imei
		inner join user u on ut.user_user_id=u.user_id    
    WHERE s.data_date BETWEEN p_date_from AND p_date_to
		AND trim(u.login)=trim(p_login)
		AND (
			p_status IS NULL
		 OR p_status = ''
		 OR s.gps_status = p_status
		)
		AND (
			p_imeis IS NULL
		 OR p_imeis = ''
		 OR FIND_IN_SET(s.imei, p_imeis)
		);

    /* result set 1: summary */
    SELECT
        COUNT(*) AS total_rows,
        COUNT(DISTINCT imei) AS total_vehicle,
        COALESCE(SUM(duration_s), 0) AS duration_s
    FROM tmp_status_summary_rows;

    /* result set 2: pagination */
    SELECT
        p_page AS current_page,
        p_per_page AS per_page,
        v_offset AS offset,
        COUNT(*) AS total_rows,
        CEIL(COUNT(*) / p_per_page) AS total_pages
    FROM tmp_status_summary_rows;

    /* result set 3: rows */
    SET @sql = CONCAT(
        'SELECT * FROM tmp_status_summary_rows ',
        'ORDER BY ', v_sort_field, ' ', v_sort_order, ', start_time ',
        'LIMIT ', p_per_page, ' OFFSET ', v_offset
    );

    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    DROP TEMPORARY TABLE IF EXISTS tmp_status_summary_rows;
END $$

DELIMITER ;