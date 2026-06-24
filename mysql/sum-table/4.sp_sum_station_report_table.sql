DELIMITER $$

DROP PROCEDURE IF EXISTS sp_sum_station_report_table $$
CREATE PROCEDURE sp_sum_station_report_table(
  IN p_table_no INT,
  IN p_sum_date DATE
)
BEGIN

  DECLARE v_data_table VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;
  DECLARE v_station_table VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;

  IF p_table_no = 0 THEN
    SET v_data_table = 'data_report';
    SET v_station_table = 'station_data';
  ELSE
    SET v_data_table = CONCAT('data_report_', p_table_no);
    SET v_station_table = CONCAT('station_data_', p_table_no);
  END IF;

  DELETE s
  FROM gps_sum_station s
  INNER JOIN tracker t 
    ON t.imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
  WHERE s.data_date = p_sum_date
    AND t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci;

  DROP TEMPORARY TABLE IF EXISTS tmp_station_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered_next;

  CREATE TEMPORARY TABLE tmp_station_points (
    seq BIGINT NOT NULL AUTO_INCREMENT,
    station_data_id INT NOT NULL,
    imei VARCHAR(20) NOT NULL,
    data_date DATETIME NOT NULL,
    station_id INT NOT NULL,
    station_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (seq),
    KEY idx_imei_seq (imei, seq),
    KEY idx_imei_station_date (imei, station_id, data_date)
  ) ENGINE=InnoDB;

  SET @sql_station = CONCAT(
    'INSERT INTO tmp_station_points ',
    '(station_data_id, imei, data_date, station_id, station_name) ',
    'SELECT ',
    'sd.station_data_id, ',
    'sd.imei, ',
    'DATE_ADD(sd.data_date, INTERVAL 7 HOUR) AS data_date, ',
    'sd.station_station_id, ',
    's.station_name ',
    'FROM ', v_station_table, ' sd ',
    'INNER JOIN tracker t ON t.imei = sd.imei ',
    'INNER JOIN stations s ON sd.station_station_id = s.station_id ',
    'WHERE t.report_table = ? ',
    'AND DATE_ADD(sd.data_date, INTERVAL 7 HOUR) >= ? ',
    'AND DATE_ADD(sd.data_date, INTERVAL 7 HOUR) < DATE_ADD(?, INTERVAL 1 DAY) ',
    'AND EXISTS ( ',
    '  SELECT 1 ',
    '  FROM customer_tracker ct ',
    '  INNER JOIN customer c ON c.customer_id = ct.customer_customer_id ',
    '  WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci ',
    '    AND c.summary_report = 1 ',
    ') ',
    'ORDER BY sd.imei, sd.station_station_id, sd.data_date, sd.station_data_id'
  );

  SET @p_report_table = v_data_table;
  SET @p_date1 = p_sum_date;
  SET @p_date2 = p_sum_date;

  PREPARE stmt_station FROM @sql_station;
  EXECUTE stmt_station USING @p_report_table, @p_date1, @p_date2;
  DEALLOCATE PREPARE stmt_station;

  CREATE TEMPORARY TABLE tmp_station_ordered (
    seq2 BIGINT NOT NULL AUTO_INCREMENT,
    station_data_id INT NOT NULL,
    imei VARCHAR(20) NOT NULL,
    data_date DATETIME NOT NULL,
    station_id INT NOT NULL,
    station_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (seq2),
    KEY idx_imei_station_seq (imei, station_id, seq2),
    KEY idx_imei_station_date (imei, station_id, data_date)
  ) ENGINE=InnoDB;

  INSERT INTO tmp_station_ordered (
    station_data_id, imei, data_date, station_id, station_name
  )
  SELECT
    station_data_id,
    imei,
    data_date,
    station_id,
    station_name
  FROM tmp_station_points
  ORDER BY imei, station_id, data_date, station_data_id;

  CREATE TEMPORARY TABLE tmp_station_ordered_next
  LIKE tmp_station_ordered;

  INSERT INTO tmp_station_ordered_next
  SELECT *
  FROM tmp_station_ordered;

  INSERT INTO gps_sum_station (
    imei,
    data_date,
    station_id,
    station_name,
    start_time,
    end_time,
    duration_s,
    updated_at
  )
  SELECT
    z.imei,
    p_sum_date,
    z.station_id,
    MAX(z.station_name) AS station_name,
    MIN(z.data_date) AS start_time,
    MAX(z.next_time) AS end_time,
    SUM(z.time_diff_s) AS duration_s,
    NOW()
  FROM (
    SELECT
      y.*,
      @st_grp := IF(
        @prev_st_imei = y.imei
        AND @prev_station = y.station_id
        AND y.time_diff_s BETWEEN 1 AND 1800,
        @st_grp,
        @st_grp + 1
      ) AS grp,
      @prev_st_imei := y.imei,
      @prev_station := y.station_id
    FROM (
      SELECT
        a.imei,
        a.station_id,
        a.station_name,
        a.data_date,
        b.data_date AS next_time,
        TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) AS time_diff_s
      FROM tmp_station_ordered a
      INNER JOIN tmp_station_ordered_next b
        ON b.imei = a.imei
       AND b.station_id = a.station_id
       AND b.seq2 = a.seq2 + 1
      WHERE TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) BETWEEN 1 AND 1800
      ORDER BY a.imei, a.station_id, a.data_date, a.station_data_id
    ) y
    CROSS JOIN (
      SELECT
        @st_grp := 0,
        @prev_st_imei := '',
        @prev_station := -1
    ) vars
  ) z
  GROUP BY z.imei, z.station_id, z.grp;

  DROP TEMPORARY TABLE IF EXISTS tmp_station_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered_next;

END $$


DROP PROCEDURE IF EXISTS sp_sum_station_daily $$
CREATE PROCEDURE sp_sum_station_daily(
  IN p_sum_date DATE
)
BEGIN
  DECLARE v_table_no INT DEFAULT 0;

  WHILE v_table_no <= 50 DO
    CALL sp_sum_station_report_table(v_table_no, p_sum_date);
    SET v_table_no = v_table_no + 1;
  END WHILE;
END $$


DROP EVENT IF EXISTS ev_sum_station_report_daily $$
CREATE EVENT ev_sum_station_report_daily
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '03:00:00')
DO
BEGIN
  CALL sp_sum_station_daily(DATE_SUB(CURDATE(), INTERVAL 1 DAY));
END $$

DELIMITER ;