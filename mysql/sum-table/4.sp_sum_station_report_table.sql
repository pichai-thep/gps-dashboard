DELIMITER $$

DROP PROCEDURE IF EXISTS sp_sum_station_report_table $$
DROP PROCEDURE IF EXISTS sp_sum_station_report_table_core $$
CREATE PROCEDURE sp_sum_station_report_table_core(
  IN p_table_no INT,
  IN p_sum_date DATE,
  IN p_customer_id INT,
  IN p_imei VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci
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
    AND t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    AND (p_imei IS NULL OR s.imei COLLATE utf8_general_ci = p_imei COLLATE utf8_general_ci)
    AND (
      p_customer_id IS NULL
      OR EXISTS (
        SELECT 1
        FROM customer_tracker ct
        WHERE ct.tracker_imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
          AND ct.customer_customer_id = p_customer_id
      )
    );

  DROP TEMPORARY TABLE IF EXISTS tmp_station_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered_next;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_latest_gps;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_distance_source;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_distance_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_distance_points_next;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_visits;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_visits_next;

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
    'AND (? IS NULL OR t.imei = ?) ',
    'AND EXISTS ( ',
    '  SELECT 1 ',
    '  FROM customer_tracker ct ',
    '  INNER JOIN customer c ON c.customer_id = ct.customer_customer_id ',
    '  WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci ',
    '    AND c.summary_report = 1 ',
    '    AND (? IS NULL OR ct.customer_customer_id = ?) ',
    ') ',
    'ORDER BY sd.imei, sd.station_station_id, sd.data_date, sd.station_data_id'
  );

  SET @p_report_table = v_data_table;
  SET @p_date1 = p_sum_date;
  SET @p_date2 = p_sum_date;
  SET @p_imei1 = p_imei;
  SET @p_imei2 = p_imei;
  SET @p_customer_id1 = p_customer_id;
  SET @p_customer_id2 = p_customer_id;

  PREPARE stmt_station FROM @sql_station;
  EXECUTE stmt_station USING @p_report_table, @p_date1, @p_date2,
    @p_imei1, @p_imei2, @p_customer_id1, @p_customer_id2;
  DEALLOCATE PREPARE stmt_station;

  CREATE TEMPORARY TABLE tmp_station_latest_gps (
    imei VARCHAR(20) NOT NULL,
    last_data_date DATETIME NOT NULL,
    PRIMARY KEY (imei)
  ) ENGINE=InnoDB;

  SET @sql_latest_station_gps = CONCAT(
    'INSERT INTO tmp_station_latest_gps (imei, last_data_date) ',
    'SELECT ',
    '  g.box_imei, ',
    '  MAX(DATE_ADD(g.data_date, INTERVAL 7 HOUR)) ',
    'FROM ', v_data_table, ' g ',
    'INNER JOIN tracker t ON t.imei = g.box_imei ',
    'WHERE t.report_table = ? ',
    'AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) >= ? ',
    'AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) < DATE_ADD(?, INTERVAL 1 DAY) ',
    'AND (? IS NULL OR t.imei = ?) ',
    'AND EXISTS ( ',
    '  SELECT 1 ',
    '  FROM customer_tracker ct ',
    '  INNER JOIN customer c ON c.customer_id = ct.customer_customer_id ',
    '  WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci ',
    '    AND c.summary_report = 1 ',
    '    AND (? IS NULL OR ct.customer_customer_id = ?) ',
    ') ',
    'GROUP BY g.box_imei'
  );

  PREPARE stmt_latest_station_gps FROM @sql_latest_station_gps;
  EXECUTE stmt_latest_station_gps USING @p_report_table, @p_date1, @p_date2,
    @p_imei1, @p_imei2, @p_customer_id1, @p_customer_id2;
  DEALLOCATE PREPARE stmt_latest_station_gps;

  INSERT INTO tmp_station_latest_gps (imei, last_data_date)
  SELECT
    g.box_imei,
    MAX(DATE_ADD(g.data_date, INTERVAL 7 HOUR))
  FROM gps_data g
  INNER JOIN tracker t ON t.imei = g.box_imei
  WHERE t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) >= p_sum_date
    AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) < DATE_ADD(p_sum_date, INTERVAL 1 DAY)
    AND (p_imei IS NULL OR t.imei COLLATE utf8_general_ci = p_imei COLLATE utf8_general_ci)
    AND EXISTS (
      SELECT 1
      FROM customer_tracker ct
      INNER JOIN customer c ON c.customer_id = ct.customer_customer_id
      WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci
        AND c.summary_report = 1
        AND (p_customer_id IS NULL OR ct.customer_customer_id = p_customer_id)
    )
  GROUP BY g.box_imei
  ON DUPLICATE KEY UPDATE
    last_data_date = GREATEST(last_data_date, VALUES(last_data_date));

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
    CASE
      WHEN p_sum_date = DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR))
       AND MAX(lg.last_data_date) IS NOT NULL
       AND MAX(z.next_time) >= MAX(lg.last_data_date)
      THEN NULL
      ELSE MAX(z.next_time)
    END AS end_time,
    SUM(z.time_diff_s) AS duration_s,
    NOW()
  FROM (
    SELECT
      y.*,
      @st_grp := IF(
        @prev_st_imei = y.imei
        AND @prev_station = y.station_id
        AND @prev_st_next_time = y.data_date
        AND y.time_diff_s BETWEEN 1 AND 600,
        @st_grp,
        @st_grp + 1
      ) AS grp,
      @prev_st_imei := y.imei,
      @prev_station := y.station_id,
      @prev_st_next_time := y.next_time
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
      WHERE TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) BETWEEN 1 AND 600
      ORDER BY a.imei, a.station_id, a.data_date, a.station_data_id
    ) y
    CROSS JOIN (
      SELECT
        @st_grp := 0,
        @prev_st_imei := '',
        @prev_station := -1,
        @prev_st_next_time := NULL
    ) vars
  ) z
  LEFT JOIN tmp_station_latest_gps lg
    ON lg.imei = z.imei
  GROUP BY z.imei, z.station_id, z.grp;

  CREATE TEMPORARY TABLE tmp_station_distance_source (
    gpsdata_id INT NOT NULL,
    imei VARCHAR(20) NOT NULL,
    data_date DATETIME NOT NULL,
    g_point POINT NOT NULL,
    PRIMARY KEY (gpsdata_id),
    KEY idx_station_distance_source (imei, data_date, gpsdata_id)
  ) ENGINE=InnoDB;

  SET @sql_station_distance_source = CONCAT(
    'INSERT IGNORE INTO tmp_station_distance_source ',
    '(gpsdata_id, imei, data_date, g_point) ',
    'SELECT ',
    '  g.gpsdata_id, ',
    '  g.box_imei, ',
    '  DATE_ADD(g.data_date, INTERVAL 7 HOUR), ',
    '  g.g_point ',
    'FROM ', v_data_table, ' g ',
    'INNER JOIN tracker t ON t.imei = g.box_imei ',
    'WHERE t.report_table = ? ',
    'AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) >= ? ',
    'AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) < DATE_ADD(?, INTERVAL 1 DAY) ',
    'AND g.g_point IS NOT NULL ',
    'AND (? IS NULL OR t.imei = ?) ',
    'AND EXISTS ( ',
    '  SELECT 1 ',
    '  FROM customer_tracker ct ',
    '  INNER JOIN customer c ON c.customer_id = ct.customer_customer_id ',
    '  WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci ',
    '    AND c.summary_report = 1 ',
    '    AND (? IS NULL OR ct.customer_customer_id = ?) ',
    ')'
  );

  PREPARE stmt_station_distance_source FROM @sql_station_distance_source;
  EXECUTE stmt_station_distance_source USING @p_report_table, @p_date1, @p_date2,
    @p_imei1, @p_imei2, @p_customer_id1, @p_customer_id2;
  DEALLOCATE PREPARE stmt_station_distance_source;

  INSERT IGNORE INTO tmp_station_distance_source (
    gpsdata_id,
    imei,
    data_date,
    g_point
  )
  SELECT
    g.gpsdata_id,
    g.box_imei,
    DATE_ADD(g.data_date, INTERVAL 7 HOUR),
    g.g_point
  FROM gps_data g
  INNER JOIN tracker t ON t.imei = g.box_imei
  WHERE t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) >= p_sum_date
    AND DATE_ADD(g.data_date, INTERVAL 7 HOUR) < DATE_ADD(p_sum_date, INTERVAL 1 DAY)
    AND g.g_point IS NOT NULL
    AND (p_imei IS NULL OR t.imei COLLATE utf8_general_ci = p_imei COLLATE utf8_general_ci)
    AND EXISTS (
      SELECT 1
      FROM customer_tracker ct
      INNER JOIN customer c ON c.customer_id = ct.customer_customer_id
      WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci
        AND c.summary_report = 1
        AND (p_customer_id IS NULL OR ct.customer_customer_id = p_customer_id)
    );

  CREATE TEMPORARY TABLE tmp_station_distance_points (
    seq BIGINT NOT NULL AUTO_INCREMENT,
    gpsdata_id INT NOT NULL,
    imei VARCHAR(20) NOT NULL,
    data_date DATETIME NOT NULL,
    g_point POINT NOT NULL,
    PRIMARY KEY (seq),
    KEY idx_station_distance_point (imei, seq, data_date)
  ) ENGINE=InnoDB;

  INSERT INTO tmp_station_distance_points (
    gpsdata_id,
    imei,
    data_date,
    g_point
  )
  SELECT
    gpsdata_id,
    imei,
    data_date,
    g_point
  FROM tmp_station_distance_source
  ORDER BY imei, data_date, gpsdata_id;

  CREATE TEMPORARY TABLE tmp_station_distance_points_next
  LIKE tmp_station_distance_points;

  INSERT INTO tmp_station_distance_points_next
  SELECT *
  FROM tmp_station_distance_points;

  CREATE TEMPORARY TABLE tmp_station_visits (
    seq BIGINT NOT NULL AUTO_INCREMENT,
    summary_id BIGINT NOT NULL,
    imei VARCHAR(20) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    PRIMARY KEY (seq),
    KEY idx_station_visit (imei, seq, start_time)
  ) ENGINE=InnoDB;

  INSERT INTO tmp_station_visits (
    summary_id,
    imei,
    start_time,
    end_time
  )
  SELECT
    s.id,
    s.imei,
    s.start_time,
    s.end_time
  FROM gps_sum_station s
  INNER JOIN tracker t
    ON t.imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
  WHERE s.data_date = p_sum_date
    AND t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    AND (p_imei IS NULL OR s.imei COLLATE utf8_general_ci = p_imei COLLATE utf8_general_ci)
    AND (
      p_customer_id IS NULL
      OR EXISTS (
        SELECT 1
        FROM customer_tracker ct
        WHERE ct.tracker_imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
          AND ct.customer_customer_id = p_customer_id
      )
    )
  ORDER BY s.imei, s.start_time, s.id;

  CREATE TEMPORARY TABLE tmp_station_visits_next
  LIKE tmp_station_visits;

  INSERT INTO tmp_station_visits_next
  SELECT *
  FROM tmp_station_visits;

  UPDATE gps_sum_station s
  INNER JOIN (
    SELECT
      current_visit.summary_id,
      ROUND(SUM(segment.distance_m)) AS distance_from_previous_m
    FROM tmp_station_visits current_visit
    INNER JOIN tmp_station_visits_next previous_visit
      ON previous_visit.imei = current_visit.imei
     AND previous_visit.seq = current_visit.seq - 1
     AND previous_visit.end_time IS NOT NULL
    INNER JOIN (
      SELECT
        a.imei,
        a.data_date,
        b.data_date AS next_time,
        ST_Distance_Sphere(
          POINT(ST_Y(a.g_point), ST_X(a.g_point)),
          POINT(ST_Y(b.g_point), ST_X(b.g_point))
        ) AS distance_m,
        TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) AS time_diff_s
      FROM tmp_station_distance_points a
      INNER JOIN tmp_station_distance_points_next b
        ON b.imei = a.imei
       AND b.seq = a.seq + 1
      WHERE ST_X(a.g_point) BETWEEN 5 AND 21
        AND ST_Y(a.g_point) BETWEEN 97 AND 106
        AND ST_X(b.g_point) BETWEEN 5 AND 21
        AND ST_Y(b.g_point) BETWEEN 97 AND 106
    ) segment
      ON segment.imei = current_visit.imei
     AND segment.data_date >= previous_visit.end_time
     AND segment.next_time <= current_visit.start_time
     AND segment.time_diff_s BETWEEN 1 AND 300
     AND segment.distance_m <= 3000
     AND (segment.distance_m / segment.time_diff_s) * 3.6 <= 180
    GROUP BY current_visit.summary_id
  ) calculated_distance
    ON calculated_distance.summary_id = s.id
  SET s.distance_from_previous_m = calculated_distance.distance_from_previous_m;

  DROP TEMPORARY TABLE IF EXISTS tmp_station_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_ordered_next;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_latest_gps;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_distance_source;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_distance_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_distance_points_next;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_visits;
  DROP TEMPORARY TABLE IF EXISTS tmp_station_visits_next;

END $$


CREATE PROCEDURE sp_sum_station_report_table(
  IN p_table_no INT,
  IN p_sum_date DATE
)
BEGIN
  CALL sp_sum_station_report_table_core(p_table_no, p_sum_date, NULL, NULL);
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
STARTS TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '05:30:00')
DO
BEGIN
  CALL sp_sum_station_daily(DATE_SUB(CURDATE(), INTERVAL 1 DAY));
END $$

DELIMITER ;
