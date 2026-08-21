DROP PROCEDURE IF EXISTS sp_webapi_history;

DELIMITER $$

CREATE PROCEDURE sp_webapi_history(
    IN p_imei VARCHAR(20),
    IN p_start_date DATE,
    IN p_end_date DATE,
    IN p_start_time VARCHAR(5),
    IN p_end_time VARCHAR(5),
    IN p_page INT,
    IN p_per_page INT
)
    proc: begin

    DECLARE v_start DATETIME;
    DECLARE v_end DATETIME;
    DECLARE v_tracker_model VARCHAR(50);
    DECLARE v_report_table VARCHAR(100);
    DECLARE v_dlt_synch tinyint;
    DECLARE v_dlt_card_reader tinyint;
    DECLARE v_event_codes VARCHAR(100);
    DECLARE v_sql TEXT;
	DECLARE v_page INT DEFAULT 1;
	DECLARE v_per_page INT DEFAULT 1000;
	DECLARE v_offset BIGINT DEFAULT 0;

    SET v_start = STR_TO_DATE(CONCAT(p_start_date, ' ', p_start_time, ':00'), '%Y-%m-%d %H:%i:%s') - INTERVAL 7 HOUR;
	SET v_end = STR_TO_DATE(CONCAT(p_end_date, ' ', p_end_time, ':59'), '%Y-%m-%d %H:%i:%s') - INTERVAL 7 HOUR;

    SET v_page = IFNULL(p_page, 1);
	SET v_per_page = IFNULL(p_per_page, 1000);

    IF v_page < 1 THEN
		SET v_page = 1;
END IF;

	IF v_per_page < 1 THEN
		SET v_per_page = 1000;
END IF;

	IF v_per_page > 5000 THEN
		SET v_per_page = 5000;
END IF;

	SET v_offset = (v_page-1) * v_per_page;

SELECT 	tracker_model, report_table, dlt_synch, dlt_card_reader
INTO 	v_tracker_model, v_report_table, v_dlt_synch, v_dlt_card_reader
FROM 	tracker
WHERE 	imei = p_imei
    LIMIT 	1;

SET v_event_codes = CASE
        WHEN v_tracker_model IN ('T1', 'T333') THEN '35,3,11'
        WHEN v_tracker_model LIKE 'Totem%' THEN 'AA,21,22,02,03'
        WHEN v_tracker_model = 'Ruptela' THEN '7,8'
        WHEN v_tracker_model = 'Concox' THEN '12,16'
        WHEN v_tracker_model = 'FiFoTrack' THEN 'A01,4,5'
        WHEN v_tracker_model = 'iStartek' THEN '0,3,4'
        ELSE NULL
END;

    IF v_report_table IS NULL OR v_report_table = '' THEN
        SET v_report_table = 'gps_data';
END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_raw;
    DROP TEMPORARY TABLE IF EXISTS tmp_state1;
    DROP TEMPORARY TABLE IF EXISTS tmp_state2;

    CREATE TEMPORARY TABLE tmp_raw (
        gpsdata_id INT,
        imei VARCHAR(20),
        tracker_model VARCHAR(20),
        plate_no VARCHAR(50),
        data_date DATETIME,
        event_code VARCHAR(10),
        engine_volt DECIMAL(5,2),
        ext_power DECIMAL(5,2),
        ext_power_status TINYINT,
        state VARCHAR(1),
        speed MEDIUMINT,
        speed_limited MEDIUMINT,
        lat DECIMAL(12,8),
        lng DECIMAL(12,8),
        heading DECIMAL(6,2),
        gps_status VARCHAR(1),
		num_sats tinyint,
        fuel_left DECIMAL(5,2),
        temperature VARCHAR(12),
        input1 VARCHAR(1),
        input2 VARCHAR(1),
        car_status VARCHAR(10),
        address VARCHAR(255),
        track1 varchar(50),
        track3 varchar(90),

        UNIQUE KEY uk_gpsdata_id (gpsdata_id),
        KEY idx_imei_date (imei, data_date),
        KEY idx_status (car_status)
    ) ENGINE=innoDB;
--     ) ENGINE=MyISAM;
--     ) ENGINE=MEMORY;

    SET @p_imei = p_imei;
    SET @v_start = v_start;
    SET @v_end = v_end;
    SET @v_event_codes = v_event_codes;

    SET v_sql = CONCAT(
        'INSERT IGNORE INTO tmp_raw (
            gpsdata_id, imei, tracker_model, plate_no, data_date, event_code,
            engine_volt, ext_power, ext_power_status, state, speed, speed_limited,
            lat, lng, heading, gps_status, num_sats, fuel_left,
            temperature, input1, input2, car_status, address, track1, track3
        )
        SELECT
            g.gpsdata_id,
            g.box_imei,
            ', QUOTE(v_tracker_model), ',
            t.plate_no,
            g.data_date,
            g.event_code,
            t.engine_volt,
            g.ext_power,
            g.ext_power_status,
            fn_acc_state(t.tracker_model, t.input_acc, g.state, g.speed),
            IFNULL(g.speed, 0),
            t.speed_limited,
            ST_X(g.g_point),
            ST_Y(g.g_point),
            g.heading,
            g.gps_status,
            g.num_sats,
            fn_fuel_percent(t.input_fuel,t.tracker_model,g.ad,t.fuel_min_vol,t.fuel_max_vol,t.input_fuel_reverse),
            fn_temperature(t.tracker_model, g.ad, t.input_temp),
            fn_input(t.tracker_model, g.state, 1, t.input_1_reverse) as in1,
			fn_input(t.tracker_model, g.state, 2, t.input_2_reverse) as in2,
            CASE
				WHEN IFNULL(CAST(NULLIF(fn_acc_state(t.tracker_model, t.input_acc, g.state, IFNULL(g.speed,0)), '''') AS UNSIGNED), 0) = 1
					 AND IFNULL(g.speed, 0) >= 5 THEN ''RUN''

				WHEN IFNULL(CAST(NULLIF(fn_acc_state(t.tracker_model, t.input_acc, g.state, IFNULL(g.speed,0)), '''') AS UNSIGNED), 0) = 1
					 AND IFNULL(g.speed, 0) < 5 THEN ''IDLE''

				ELSE ''PARK''
			END,
            g.address, track1, track3
        FROM `', REPLACE(v_report_table, '`', ''), '` g inner join tracker t on g.box_imei=t.imei
        WHERE g.box_imei = ?
          AND g.data_date BETWEEN ? AND ?
          AND g.g_point IS NOT NULL
          AND ST_X(g.g_point) BETWEEN 5 AND 21
          AND ST_Y(g.g_point) BETWEEN 97 AND 106
          AND (? IS NULL OR FIND_IN_SET(g.event_code, ?) > 0)'
    );

    SET @v_sql = v_sql;
PREPARE stmt FROM @v_sql;
EXECUTE stmt USING @p_imei, @v_start, @v_end, @v_event_codes, @v_event_codes;
DEALLOCATE PREPARE stmt;

--     select * from tmp_raw;
--     leave proc;

INSERT IGNORE INTO tmp_raw (
    gpsdata_id, imei, tracker_model, plate_no, data_date, event_code,
    engine_volt, ext_power, ext_power_status, state, speed, speed_limited,
    lat, lng, heading, gps_status, num_sats, fuel_left,
    temperature, input1, input2, car_status, address, track1, track3
)
SELECT
    g.gpsdata_id,
    g.box_imei,
    t.tracker_model,
    t.plate_no,
    g.data_date,
    g.event_code,
    t.engine_volt,
    g.ext_power,
    g.ext_power_status,
    fn_acc_state(t.tracker_model, t.input_acc, g.state, g.speed),
    IFNULL(g.speed, 0),
    t.speed_limited,
    ST_X(g.g_point),
    ST_Y(g.g_point),
    g.heading,
    g.gps_status,
    g.num_sats,
    fn_fuel_percent(t.input_fuel, t.tracker_model,g.ad,t.fuel_min_vol,t.fuel_max_vol,t.input_fuel_reverse),
    fn_temperature(t.tracker_model, g.ad, t.input_temp),
    fn_input(t.tracker_model, g.state, t.input_1, t.input_1_reverse) as in1,
    fn_input(t.tracker_model, g.state, t.input_2, t.input_2_reverse) as in2	,
    CASE
        WHEN IFNULL(CAST(NULLIF(fn_acc_state(t.tracker_model, t.input_acc, g.state, IFNULL(g.speed,0)), '') AS UNSIGNED), 0) = 1
            AND IFNULL(g.speed, 0) >= 5 THEN 'RUN'

        WHEN IFNULL(CAST(NULLIF(fn_acc_state(t.tracker_model, t.input_acc, g.state, IFNULL(g.speed,0)), '') AS UNSIGNED), 0) = 1
            AND IFNULL(g.speed, 0) < 5 THEN 'IDLE'

        ELSE 'PARK'
        END,
    g.address, track1, track3
FROM gps_data g inner join tracker t on g.box_imei=t.imei
WHERE g.box_imei = p_imei
  AND g.data_date BETWEEN v_start AND v_end
  AND (v_event_codes IS NULL OR FIND_IN_SET(g.event_code, v_event_codes) > 0)
  AND g.g_point IS NOT NULL
  AND ST_X(g.g_point) BETWEEN 5 AND 21
  AND ST_Y(g.g_point) BETWEEN 97 AND 106
;


--  	select * from tmp_raw;
-- 	leave proc;

DROP TEMPORARY TABLE IF EXISTS tmp_state1;
    CREATE TEMPORARY TABLE tmp_state1 (
        row_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        gpsdata_id INT,
        imei VARCHAR(20),
        tracker_model VARCHAR(20),
        plate_no VARCHAR(50),
        data_date DATETIME,
        event_code VARCHAR(10),
        engine_volt DECIMAL(5,2),
        ext_power DECIMAL(5,2),
        ext_power_status TINYINT,
        state VARCHAR(1),
        speed MEDIUMINT,
        speed_limited MEDIUMINT,
        lat DECIMAL(12,8),
        lng DECIMAL(12,8),
        heading DECIMAL(6,2),
        gps_status VARCHAR(1),
        num_sats tinyint,
        fuel_left DECIMAL(5,2),
        temperature VARCHAR(12),
        input1 VARCHAR(1),
        input2 VARCHAR(1),
        car_status VARCHAR(10),
        address VARCHAR(255),
        track1 varchar(50),
        track3 varchar(90),
        duration_sec INT DEFAULT 0,
        duration_mm INT DEFAULT 0,
        segment_meter DECIMAL(12,2) DEFAULT 0,

        KEY idx_imei_date (imei, data_date),
        KEY idx_status (car_status)
	)ENGINE=InnoDB;
--     ) ENGINE=MyISAM;
-- 	) ENGINE=MEMORY;

INSERT INTO tmp_state1 (
    gpsdata_id, imei, tracker_model, plate_no, data_date, event_code,
    engine_volt, ext_power, ext_power_status, `state`, speed, speed_limited,
    lat, lng, heading, gps_status, num_sats,
    fuel_left, temperature, input1, input2, car_status, address, track1, track3
)
SELECT
    gpsdata_id, imei, tracker_model, plate_no, data_date, event_code,
    engine_volt, ext_power, ext_power_status, `state`, speed, speed_limited,
    lat, lng, heading, gps_status, num_sats,
    fuel_left, temperature, input1, input2, car_status, address, track1, track3
FROM tmp_raw
ORDER BY data_date ASC, gpsdata_id ASC;

--     select * from tmp_state1;
--     leave proc;

-- ///////////////////////////// tmp_state2  /////////////////////////////////////
DROP TEMPORARY TABLE IF EXISTS tmp_state2;

	-- CREATE TEMPORARY TABLE tmp_state2 ENGINE=MyISAM AS
    -- CREATE TEMPORARY TABLE tmp_state2 ENGINE=Memory AS
    CREATE TEMPORARY TABLE tmp_state2 ENGINE=InnoDB AS
SELECT
    row_id,
    gpsdata_id,
    imei,
    tracker_model,
    plate_no,
    data_date,
    event_code,
    engine_volt,
    ext_power,
    ext_power_status,
    `state`,
    speed,
    speed_limited,
    lat,
    lng,
    heading,
    gps_status,
    num_sats,
    fuel_left,
    temperature,
    input1,
    input2,
    car_status,
    address, track1, track3,
    duration_sec,
    duration_mm,
    segment_meter
FROM tmp_state1
ORDER BY data_date ASC, gpsdata_id ASC;
ALTER TABLE tmp_state2 ADD PRIMARY KEY (row_id);

--     SELECT COUNT(*) AS c1 FROM tmp_state1;
-- 	SELECT COUNT(*) AS c2 FROM tmp_state2;
-- 	SELECT * FROM tmp_state2 limit 100;
-- 	LEAVE proc;


UPDATE tmp_state1 cur
    LEFT JOIN tmp_state2 nxt
ON nxt.row_id = cur.row_id + 1
    SET
        cur.duration_sec = CASE
        WHEN nxt.data_date IS NULL THEN 0
        WHEN TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) < 1 THEN 0
        WHEN cur.car_status IN ('RUN', 'IDLE')
        AND TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) > 300 THEN 0
        WHEN cur.car_status = 'PARK'
        AND TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) > 1800 THEN 0
        ELSE TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date)
END,

        cur.duration_mm = CASE
			WHEN nxt.data_date IS NULL THEN 0
			WHEN TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) < 1 THEN 0
			WHEN cur.car_status IN ('RUN', 'IDLE')
				 AND TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) > 300 THEN 0
			WHEN cur.car_status = 'PARK'
				 AND TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) > 1800 THEN 0
			ELSE CEIL(TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) / 60)
END,

		cur.segment_meter = CASE
			WHEN nxt.data_date IS NULL THEN 0
			WHEN cur.lat IS NULL OR cur.lng IS NULL
			  OR nxt.lat IS NULL OR nxt.lng IS NULL THEN 0
			WHEN cur.gps_status = 'V' OR nxt.gps_status = 'V' THEN 0
			WHEN TIMESTAMPDIFF(SECOND, cur.data_date, nxt.data_date) > 3600 THEN 0
			ELSE
				6371000 * 2 * ASIN(
					SQRT(
						POWER(SIN(RADIANS(nxt.lat - cur.lat) / 2), 2) +
						COS(RADIANS(cur.lat)) *
						COS(RADIANS(nxt.lat)) *
						POWER(SIN(RADIANS(nxt.lng - cur.lng) / 2), 2)
					)
				)
END;

SELECT
    SEC_TO_TIME(IFNULL(SUM(CASE WHEN car_status = 'RUN' THEN duration_sec ELSE 0 END), 0)) AS run_time,
    SEC_TO_TIME(IFNULL(SUM(CASE WHEN car_status = 'IDLE' THEN duration_sec ELSE 0 END), 0)) AS idle_time,
    SEC_TO_TIME(IFNULL(SUM(CASE WHEN car_status = 'PARK' THEN duration_sec ELSE 0 END), 0)) AS park_time,

    IFNULL(SUM(CASE WHEN car_status = 'RUN' THEN duration_sec ELSE 0 END), 0) AS run_seconds,
    IFNULL(SUM(CASE WHEN car_status = 'IDLE' THEN duration_sec ELSE 0 END), 0) AS idle_seconds,
    IFNULL(SUM(CASE WHEN car_status = 'PARK' THEN duration_sec ELSE 0 END), 0) AS park_seconds,

    ROUND(IFNULL(SUM(segment_meter), 0) / 1000, 2) AS distance_km,
    ROUND(IFNULL(SUM(segment_meter), 0), 0) AS distance_meter,

    v_tracker_model AS tracker_model,
    v_report_table AS report_table,
    v_event_codes AS event_code_filter,
    v_dlt_synch, v_dlt_card_reader,
    COUNT(*) AS total_rows
FROM tmp_state1;

SELECT
    COUNT(*) AS total_rows,
    v_page AS page,
    v_per_page AS per_page,
    CEIL(COUNT(*) / CAST(v_per_page AS DECIMAL(10,2))) AS total_pages,
    v_offset AS offset_rows
FROM tmp_state1;

-- set v_offset=100;

SELECT gpsdata_id,
       imei,
       tracker_model,
       plate_no,
       date_add(data_date, interval 7 hour) as data_date,
       event_code,
       engine_volt,
       ext_power,
       ext_power_status,
       `state`,
       speed,
       speed_limited,
       lat,
       lng,
       heading,
       gps_status,
       num_sats,
       fuel_left,
       temperature,
       input1,
       input2,
       car_status,
       address, track1, track3,
       v_dlt_synch AS dlt_synch,
       v_dlt_card_reader AS dlt_card_reader
FROM tmp_state1
ORDER BY data_date ASC, gpsdata_id ASC
    LIMIT v_offset, v_per_page;

--     DROP TEMPORARY TABLE IF EXISTS tmp_raw;
--     DROP TEMPORARY TABLE IF EXISTS tmp_state1;
--     DROP TEMPORARY TABLE IF EXISTS tmp_state2;

END$$

DELIMITER ;
