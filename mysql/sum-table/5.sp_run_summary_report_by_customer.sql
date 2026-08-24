DELIMITER $$

DROP PROCEDURE IF EXISTS sp_run_summary_report_by_customer $$
CREATE PROCEDURE sp_run_summary_report_by_customer(
  IN p_customer_id INT,
  IN p_date_from DATE,
  IN p_date_to DATE
)
proc: BEGIN
  DECLARE v_date DATE;
  DECLARE v_table_no INT;
  DECLARE v_row_no INT DEFAULT 1;
  DECLARE v_table_count INT DEFAULT 0;
  DECLARE v_customer_count INT DEFAULT 0;

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
  FROM customer
  WHERE customer_id = p_customer_id
    AND summary_report = 1;

  IF v_customer_count = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'customer not found or summary report is disabled';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_customer_report_tables;

  CREATE TEMPORARY TABLE tmp_customer_report_tables (
    row_no INT NOT NULL AUTO_INCREMENT,
    table_no INT NOT NULL,
    PRIMARY KEY (row_no),
    UNIQUE KEY uq_table_no (table_no)
  ) ENGINE=MEMORY;

  INSERT INTO tmp_customer_report_tables (table_no)
  SELECT DISTINCT
    CASE
      WHEN TRIM(t.report_table) = 'data_report' THEN 0
      ELSE CAST(SUBSTRING(TRIM(t.report_table), 13) AS UNSIGNED)
    END AS table_no
  FROM customer_tracker ct
  INNER JOIN tracker t
    ON BINARY t.imei = BINARY ct.tracker_imei
  WHERE ct.customer_customer_id = p_customer_id
    AND (
      TRIM(t.report_table) = 'data_report'
      OR TRIM(t.report_table) REGEXP '^data_report_[0-9]+$'
    );

  SELECT COUNT(*)
    INTO v_table_count
  FROM tmp_customer_report_tables;

  IF v_table_count = 0 THEN
    DROP TEMPORARY TABLE IF EXISTS tmp_customer_report_tables;
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'customer has no tracker report tables';
  END IF;

  SET v_date = p_date_from;

  WHILE v_date <= p_date_to DO
    SET v_row_no = 1;

    WHILE v_row_no <= v_table_count DO
      SELECT table_no
        INTO v_table_no
      FROM tmp_customer_report_tables
      WHERE row_no = v_row_no;

      CALL sp_sum_report_table(v_table_no, v_date, p_customer_id);

      SET v_row_no = v_row_no + 1;
    END WHILE;

    SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
  END WHILE;

  DROP TEMPORARY TABLE IF EXISTS tmp_customer_report_tables;
END $$

DELIMITER ;

-- Example: rebuild one customer's summary data for an inclusive date range.
-- CALL sp_run_summary_report_by_customer(123, '2026-08-01', '2026-08-07');
