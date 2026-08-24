DELIMITER $$

DROP PROCEDURE IF EXISTS sp_sum_report_table $$
CREATE PROCEDURE sp_sum_report_table(
  IN p_table_no INT,
  IN p_sum_date DATE,
  IN p_customer_id INT
)
proc: BEGIN

  DECLARE v_data_table VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;
  DECLARE v_log_id BIGINT DEFAULT 0;
  DECLARE v_sqlstate CHAR(5) DEFAULT '00000';
  DECLARE v_mysql_errno INT DEFAULT 0;
  DECLARE v_error_message TEXT;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1
      v_sqlstate = RETURNED_SQLSTATE,
      v_mysql_errno = MYSQL_ERRNO,
      v_error_message = MESSAGE_TEXT;

    UPDATE gps_sum_log
    SET end_time = NOW(),
        status = 'ERROR',
        error_message = CONCAT(
          'customer_id=', COALESCE(CAST(p_customer_id AS CHAR), 'ALL'),
          ', table=', COALESCE(v_data_table, CONCAT('table_no:', p_table_no)),
          ', date=', COALESCE(CAST(p_sum_date AS CHAR), 'NULL'),
          ', SQLSTATE=', v_sqlstate,
          ', errno=', v_mysql_errno,
          ', message=', COALESCE(v_error_message, 'Unknown SQL exception')
        )
    WHERE id = v_log_id;

    DROP TEMPORARY TABLE IF EXISTS tmp_day_points;
    DROP TEMPORARY TABLE IF EXISTS tmp_day_points_next;

    RESIGNAL;
  END;

  IF p_table_no = 0 THEN
    SET v_data_table = 'data_report';
  ELSE
    SET v_data_table = CONCAT('data_report_', p_table_no);
  END IF;

  INSERT INTO gps_sum_log (process_date, table_no, start_time, status)
  VALUES (p_sum_date, p_table_no, NOW(), 'RUNNING');

  SET v_log_id = LAST_INSERT_ID();

  DELETE s
  FROM gps_sum_data s
  INNER JOIN tracker t ON t.imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
  WHERE s.data_date = p_sum_date
    AND t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    AND (
      p_customer_id IS NULL
      OR EXISTS (
        SELECT 1
        FROM customer_tracker ct
        WHERE ct.tracker_imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
          AND ct.customer_customer_id = p_customer_id
      )
    );

  DELETE s
  FROM gps_sum_status s
  INNER JOIN tracker t ON t.imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
  WHERE s.data_date = p_sum_date
    AND t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    AND (
      p_customer_id IS NULL
      OR EXISTS (
        SELECT 1
        FROM customer_tracker ct
        WHERE ct.tracker_imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
          AND ct.customer_customer_id = p_customer_id
      )
    );

  DROP TEMPORARY TABLE IF EXISTS tmp_day_points;
  DROP TEMPORARY TABLE IF EXISTS tmp_day_points_next;

  CREATE TEMPORARY TABLE tmp_day_points (
    seq BIGINT NOT NULL AUTO_INCREMENT,
    gpsdata_id INT NOT NULL,
    imei VARCHAR(20) NOT NULL,
    data_date DATETIME NOT NULL,
    speed INT DEFAULT 0,
    g_point POINT DEFAULT NULL,
    acc_on TINYINT DEFAULT 0,
    track3 VARCHAR(90) DEFAULT NULL,
    status_name VARCHAR(20) NOT NULL,
    PRIMARY KEY (seq),
    KEY idx_imei_seq (imei, seq),
    KEY idx_imei_date (imei, data_date)
  ) ENGINE=InnoDB;

  SET @sql = CONCAT(
	  'INSERT INTO tmp_day_points ',
	  '(gpsdata_id, imei, data_date, speed, g_point, acc_on, track3, status_name) ',
	  'SELECT ',
	  'x.gpsdata_id, ',
	  'x.imei, ',
	  'date_add(x.data_date, interval 7 hour) as data_date, ',
	  'x.speed, ',
	  'x.g_point, ',
	  'x.acc_on, ',
	  'x.track3, ',
	  'CASE ',
	  ' WHEN x.acc_on = 1 AND x.speed >= 5 THEN ''RUN'' ',
	  ' WHEN x.acc_on = 1 AND x.speed < 5 THEN ''IDLE'' ',
      ' WHEN x.acc_on = 0 THEN ''PARK'' ',
	  ' ELSE ''PARK'' ',
	  'END AS status_name ',
	  'FROM ( ',
	  ' SELECT ',
	  '  d.gpsdata_id, ',
	  '  d.box_imei AS imei, ',
	  '  d.data_date, ',
	  '  IFNULL(d.speed,0) AS speed, ',
	  '  d.g_point, ',
	  '  d.track3, ',
	  '  IFNULL(CAST(NULLIF(fn_acc_state(t.tracker_model,t.input_acc,d.state,IFNULL(d.speed,0)), '''') AS UNSIGNED),0) AS acc_on ',
	  ' FROM ', v_data_table, ' d ',
	  ' INNER JOIN tracker t ON t.imei = d.box_imei ',
	  ' WHERE t.report_table = ? ',
	  ' AND date_add(d.data_date, interval 7 hour) >= ? AND date_add(d.data_date, interval 7 hour) < DATE_ADD(?, INTERVAL 1 DAY) ',
	  ' AND d.g_point IS NOT NULL ',

	  -- filter event_code ตาม tracker_model
	  ' AND (NULLIF(TRIM(d.track3), '''') IS NOT NULL OR ( ',
	  '   CASE ',
	  '     WHEN t.tracker_model IN (''T1'', ''T333'') THEN FIND_IN_SET(d.event_code, ''35,3,11'') ',
	  '     WHEN t.tracker_model LIKE ''Totem%'' THEN FIND_IN_SET(d.event_code, ''AA,21,22,02,03'') ',
	  '     WHEN t.tracker_model = ''Ruptela'' THEN FIND_IN_SET(d.event_code, ''7,8'') ',
	  '     WHEN t.tracker_model = ''Concox'' THEN FIND_IN_SET(d.event_code, ''12,16'') ',
	  '     WHEN t.tracker_model = ''FiFoTrack'' THEN FIND_IN_SET(d.event_code, ''A01,4,5'') ',
	  '     WHEN t.tracker_model = ''iStartek'' THEN FIND_IN_SET(d.event_code, ''0'') ',
	  '     ELSE 1 ',
	  '   END ',
	  ' ) > 0) ',

	  ' AND EXISTS ( ',
	  '   SELECT 1 ',
	  '   FROM customer_tracker ct ',
	  '   INNER JOIN customer c ON c.customer_id = ct.customer_customer_id ',
	  '   WHERE ct.tracker_imei COLLATE utf8_general_ci = t.imei COLLATE utf8_general_ci ',
	  '     AND c.summary_report = 1 ',
	  '     AND (? IS NULL OR ct.customer_customer_id = ?) ',
	  ' ) ',
	  ') x ',
	  'ORDER BY x.imei, x.data_date, x.gpsdata_id'
	);

  SET @p_report_table = v_data_table;
  SET @p_date1 = p_sum_date;
  SET @p_date2 = p_sum_date;
  SET @p_customer_id1 = p_customer_id;
  SET @p_customer_id2 = p_customer_id;

  PREPARE stmt FROM @sql;
  EXECUTE stmt USING @p_report_table, @p_date1, @p_date2, @p_customer_id1, @p_customer_id2;
  DEALLOCATE PREPARE stmt;

  CREATE TEMPORARY TABLE tmp_day_points_next LIKE tmp_day_points;

  INSERT INTO tmp_day_points_next
  SELECT * FROM tmp_day_points;
  -- leave proc;

  INSERT INTO gps_sum_data (
    imei, data_date, run_time_s, run_withid_time_s, idle_time_s, park_time_s,
    distance_m, distance_withid_m, total_rows, updated_at
  )
  SELECT
    x.imei,
    p_sum_date,

    SUM(CASE WHEN x.status_name = 'RUN'
              AND x.time_diff_s BETWEEN 1 AND 300
             THEN x.time_diff_s ELSE 0 END),

    SUM(CASE WHEN x.status_name = 'RUN'
              AND (NULLIF(TRIM(x.track3), '') IS NOT NULL
                   OR NULLIF(TRIM(x.next_track3), '') IS NOT NULL)
              AND x.time_diff_s BETWEEN 1 AND 300
             THEN x.time_diff_s ELSE 0 END),

    SUM(CASE WHEN x.status_name = 'IDLE'
              AND x.time_diff_s BETWEEN 1 AND 300
             THEN x.time_diff_s ELSE 0 END),

	SUM(CASE WHEN x.status_name = 'PARK'
          AND x.time_diff_s BETWEEN 1 AND 1800
         THEN x.time_diff_s ELSE 0 END),

    ROUND(SUM(
      CASE
        WHEN (x.speed >= 5 OR x.next_speed >= 5)
				AND x.time_diff_s BETWEEN 1 AND 300
				AND x.distance_m <= 3000
				AND (x.distance_m / x.time_diff_s) * 3.6 <= 180         
        THEN x.distance_m
        ELSE 0
      END
    )),

    ROUND(SUM(
      CASE
        WHEN (NULLIF(TRIM(x.track3), '') IS NOT NULL
              OR NULLIF(TRIM(x.next_track3), '') IS NOT NULL)
                AND (x.speed >= 5 OR x.next_speed >= 5)
				AND x.time_diff_s BETWEEN 1 AND 300
				AND x.distance_m <= 3000
				AND (x.distance_m / x.time_diff_s) * 3.6 <= 180
        THEN x.distance_m
        ELSE 0
      END
    )),

    COUNT(x.seq),
    NOW()
  FROM (
    SELECT	  
	  a.seq,
      a.imei,
      a.status_name,
      a.track3,
      b.track3 AS next_track3,
      a.speed,
      b.speed AS next_speed,
      TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) AS time_diff_s,
      ST_Distance_Sphere(
        POINT(ST_Y(a.g_point), ST_X(a.g_point)),
        POINT(ST_Y(b.g_point), ST_X(b.g_point))
      ) AS distance_m
    FROM tmp_day_points a
    INNER JOIN tmp_day_points_next b
      ON b.imei = a.imei
     AND b.seq = a.seq + 1
    WHERE ST_X(a.g_point) BETWEEN 5 AND 21
      AND ST_Y(a.g_point) BETWEEN 97 AND 106
      AND ST_X(b.g_point) BETWEEN 5 AND 21
      AND ST_Y(b.g_point) BETWEEN 97 AND 106
  ) x
  GROUP BY x.imei;

  INSERT INTO gps_sum_status (
	  imei, data_date, gps_status, start_time, end_time, duration_s, updated_at
	)
	SELECT
	  z.imei,
	  p_sum_date,
	  z.status_name,
	  MIN(z.data_date) AS start_time,
	  MAX(z.next_time) AS end_time,
	  SUM(z.time_diff_s) AS duration_s,
	  NOW()
	FROM (
	  SELECT
		y.*,
		@grp := IF(
		  @prev_imei = y.imei
		  AND @prev_status = y.status_name
		  AND @prev_next_time = y.data_date
		  AND y.time_diff_s <= y.max_gap_s,
		  @grp,
		  @grp + 1
		) AS grp,
		@prev_imei := y.imei,
		@prev_status := y.status_name,
		@prev_next_time := y.next_time
	  FROM (
		SELECT
		  a.imei,
		  a.status_name,
		  a.data_date,
		  b.data_date AS next_time,
		  TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) AS time_diff_s,
		  CASE
			WHEN a.status_name = 'PARK' THEN 1800
			ELSE 300
		  END AS max_gap_s
		FROM tmp_day_points a
		INNER JOIN tmp_day_points_next b
		  ON b.imei = a.imei
		 AND b.seq = a.seq + 1
		WHERE TIMESTAMPDIFF(SECOND, a.data_date, b.data_date) BETWEEN 1 AND
		  CASE
			WHEN a.status_name = 'PARK' THEN 1800
			ELSE 300
		  END
		ORDER BY a.imei, a.data_date, a.gpsdata_id
	  ) y
	  CROSS JOIN (
		SELECT @grp := 0, @prev_imei := '', @prev_status := '', @prev_next_time := NULL
	  ) vars
	) z
	GROUP BY z.imei, z.status_name, z.grp;

  UPDATE gps_sum_data s
  LEFT JOIN (
    SELECT
      imei,
      SUM(CASE WHEN gps_status = 'PARK' THEN 1 ELSE 0 END) AS park_count,
      SUM(CASE
            WHEN gps_status = 'IDLE' AND duration_s > 300 THEN 1
            ELSE 0
          END) AS idle_over_5m_count
    FROM gps_sum_status
    WHERE data_date = p_sum_date
    GROUP BY imei
  ) status_count ON status_count.imei = s.imei
  SET
    s.park_count = COALESCE(status_count.park_count, 0),
    s.idle_over_5m_count = COALESCE(status_count.idle_over_5m_count, 0)
  WHERE s.data_date = p_sum_date
    AND EXISTS (
      SELECT 1
      FROM tracker t
      WHERE t.imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
        AND t.report_table COLLATE utf8_general_ci = v_data_table COLLATE utf8_general_ci
    )
    AND (
      p_customer_id IS NULL
      OR EXISTS (
        SELECT 1
        FROM customer_tracker ct
        WHERE ct.tracker_imei COLLATE utf8_general_ci = s.imei COLLATE utf8_general_ci
          AND ct.customer_customer_id = p_customer_id
      )
    );
  
  UPDATE gps_sum_log
  SET end_time = NOW(),
      rows_processed = (SELECT COUNT(*) FROM tmp_day_points),
      status = 'DONE'
  WHERE id = v_log_id;

DROP TEMPORARY TABLE IF EXISTS tmp_day_points;
DROP TEMPORARY TABLE IF EXISTS tmp_day_points_next;

END $$


DROP PROCEDURE IF EXISTS sp_sum_gps_report_daily $$
CREATE PROCEDURE sp_sum_gps_report_daily(
  IN p_sum_date DATE
)
BEGIN
  DECLARE v_table_no INT DEFAULT 0;

  WHILE v_table_no <= 50 DO
    CALL sp_sum_report_table(v_table_no, p_sum_date, NULL);
    SET v_table_no = v_table_no + 1;
  END WHILE;
END $$


DROP EVENT IF EXISTS ev_sum_gps_report_daily $$
CREATE EVENT ev_sum_gps_report_daily
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '02:00:00')
DO
BEGIN
  CALL sp_sum_gps_report_daily(DATE_SUB(CURDATE(), INTERVAL 1 DAY));
END $$

DELIMITER ;
