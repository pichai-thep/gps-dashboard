DELIMITER $$

DROP PROCEDURE IF EXISTS sp_rebuild_station_report_by_customer $$

CREATE PROCEDURE sp_rebuild_station_report_by_customer(
    IN p_customer_id INT,
    IN p_date_from DATE,
    IN p_date_to DATE
)
proc: BEGIN
    DECLARE v_row_no INT DEFAULT 1;
    DECLARE v_tracker_count INT DEFAULT 0;
    DECLARE v_customer_count INT DEFAULT 0;
    DECLARE v_imei VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_customer_imeis;
        RESIGNAL;
    END;

    IF p_customer_id IS NULL OR p_customer_id <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'customer_id must be greater than zero';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'date_from and date_to are required';
    END IF;

    IF p_date_from > p_date_to THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'date_from must not be after date_to';
    END IF;

    SELECT COUNT(*)
      INTO v_customer_count
    FROM customer c
    WHERE c.customer_id = p_customer_id
      AND c.summary_report = 1;

    IF v_customer_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'customer not found or summary report is disabled';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_customer_imeis;
    CREATE TEMPORARY TABLE tmp_station_rebuild_customer_imeis (
        row_no INT NOT NULL AUTO_INCREMENT,
        imei VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
        PRIMARY KEY (row_no),
        UNIQUE KEY uq_station_rebuild_customer_imei (imei)
    ) ENGINE=MEMORY;

    INSERT INTO tmp_station_rebuild_customer_imeis (imei)
    SELECT DISTINCT TRIM(t.imei)
    FROM customer_tracker ct
    INNER JOIN tracker t
        ON t.imei COLLATE utf8_general_ci = ct.tracker_imei COLLATE utf8_general_ci
    WHERE ct.customer_customer_id = p_customer_id
      AND TRIM(t.imei) <> ''
      AND (
        TRIM(t.report_table) = 'data_report'
        OR TRIM(t.report_table) REGEXP '^data_report_[0-9]+$'
      )
    ORDER BY TRIM(t.imei);

    SELECT COUNT(*)
      INTO v_tracker_count
    FROM tmp_station_rebuild_customer_imeis;

    IF v_tracker_count = 0 THEN
        DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_customer_imeis;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'customer has no tracker report tables';
    END IF;

    WHILE v_row_no <= v_tracker_count DO
        SELECT imei
          INTO v_imei
        FROM tmp_station_rebuild_customer_imeis
        WHERE row_no = v_row_no;

        CALL sp_rebuild_station_data_by_imei_core(
            v_imei,
            p_date_from,
            p_date_to,
            0,
            0
        );

        SET v_row_no = v_row_no + 1;
    END WHILE;

    CALL sp_run_station_summary_report_by_customer(
        p_customer_id,
        p_date_from,
        p_date_to
    );

    DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_customer_imeis;

    SELECT
        p_customer_id AS customer_id,
        p_date_from AS date_from,
        p_date_to AS date_to,
        v_tracker_count AS tracker_count,
        'COMPLETED' AS status;
END $$

DELIMITER ;

-- Rebuild raw station membership and gps_sum_station in one call.
-- CALL sp_rebuild_station_report_by_customer(123, '2026-08-01', '2026-08-07');
