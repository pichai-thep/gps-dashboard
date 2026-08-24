DELIMITER $$

DROP PROCEDURE IF EXISTS sp_run_summary_report_by_imei $$
CREATE PROCEDURE sp_run_summary_report_by_imei(
  IN p_imei VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci,
  IN p_date_from DATE,
  IN p_date_to DATE
)
proc: BEGIN
  DECLARE v_date DATE;
  DECLARE v_data_table VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;
  DECLARE v_table_no INT;

  IF p_imei IS NULL OR TRIM(p_imei) = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'imei is required';
  END IF;

  IF p_date_from IS NULL OR p_date_to IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'date_from and date_to are required';
  END IF;

  IF p_date_from > p_date_to THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'date_from must not be after date_to';
  END IF;

  SELECT MAX(TRIM(t.report_table))
    INTO v_data_table
  FROM tracker t
  WHERE t.imei COLLATE utf8_general_ci = TRIM(p_imei) COLLATE utf8_general_ci;

  IF v_data_table IS NULL OR NOT (
    v_data_table = 'data_report'
    OR v_data_table REGEXP '^data_report_[0-9]+$'
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'imei not found or report_table is invalid';
  END IF;

  SET v_table_no = CASE
    WHEN v_data_table = 'data_report' THEN 0
    ELSE CAST(SUBSTRING(v_data_table, 13) AS UNSIGNED)
  END;

  SET v_date = p_date_from;

  WHILE v_date <= p_date_to DO
    CALL sp_sum_report_table_core(v_table_no, v_date, NULL, TRIM(p_imei));
    SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
  END WHILE;
END $$

DELIMITER ;

-- Example: rebuild one IMEI's summary data for an inclusive date range.
-- CALL sp_run_summary_report_by_imei('864606041741959', '2026-08-01', '2026-08-07');
