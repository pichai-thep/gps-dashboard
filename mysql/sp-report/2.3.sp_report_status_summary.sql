DELIMITER $$

DROP PROCEDURE IF EXISTS sp_report_status_summary $$

CREATE PROCEDURE sp_report_status_summary(
    IN p_login VARCHAR(20),
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_status VARCHAR(20),
    IN p_duration_m INT,
    IN p_imeis TEXT,
    IN p_group_ids TEXT,
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
    SET p_duration_m = GREATEST(IFNULL(p_duration_m, 0), 0);

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
            WHEN 'id'         THEN 'id'
            WHEN 'imei'       THEN 'imei'
            WHEN 'plate_no'   THEN 'plate_no'
            WHEN 'data_date'  THEN 'data_date'
            WHEN 'gps_status' THEN 'gps_status'
            WHEN 'start_time' THEN 'start_time'
            WHEN 'end_time'   THEN 'end_time'
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
    INNER JOIN tracker t
        ON t.imei = s.imei
    WHERE s.data_date BETWEEN p_date_from AND p_date_to

      /* ตรวจสิทธิ์โดยไม่เพิ่มจำนวนแถว */
      AND EXISTS (
          SELECT 1
          FROM user_tracker ut
          INNER JOIN user u
              ON u.user_id = ut.user_user_id
          WHERE ut.tracker_imei = s.imei
            AND u.login = TRIM(p_login)
      )

      AND (
             p_status IS NULL
          OR TRIM(p_status) = ''
          OR s.gps_status = TRIM(p_status)
      )

      AND s.duration_s >= p_duration_m * 60

      AND (
             p_imeis IS NULL
          OR TRIM(p_imeis) = ''
          OR FIND_IN_SET(s.imei, REPLACE(p_imeis, ' ', '')) > 0
      )

      AND (
             p_group_ids IS NULL
          OR TRIM(p_group_ids) = ''
          OR EXISTS (
              SELECT 1
              FROM customer_group_tracker cgt
              INNER JOIN customer_group cg
                  ON cg.customer_group_id = cgt.customer_group_id
              INNER JOIN customer_user cu
                  ON cu.customer_customer_id = cg.customer_id
              INNER JOIN user gu
                  ON gu.user_id = cu.user_user_id
              WHERE cgt.imei = s.imei
                AND TRIM(gu.login) = TRIM(p_login)
                AND FIND_IN_SET(
                    CAST(cgt.customer_group_id AS CHAR),
                    REPLACE(p_group_ids, ' ', '')
                ) > 0
          )
      );

    /* Result set 1: summary */
    SELECT
        COUNT(*) AS total_rows,
        COUNT(DISTINCT imei) AS total_vehicle,
        COALESCE(SUM(duration_s), 0) AS duration_s
    FROM tmp_status_summary_rows;

    /* Result set 2: pagination */
    SELECT
        p_page AS current_page,
        p_per_page AS per_page,
        v_offset AS offset,
        COUNT(*) AS total_rows,
        CEIL(COUNT(*) / p_per_page) AS total_pages
    FROM tmp_status_summary_rows;

    /* Result set 3: rows */
    SET @sql = CONCAT(
        'SELECT * FROM tmp_status_summary_rows ',
        'ORDER BY ', v_sort_field, ' ', v_sort_order,
        ', start_time ASC, id ASC ',
        'LIMIT ', p_per_page,
        ' OFFSET ', v_offset
    );

    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    DROP TEMPORARY TABLE IF EXISTS tmp_status_summary_rows;
END $$

DELIMITER ;
