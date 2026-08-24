DELIMITER $$

DROP PROCEDURE IF EXISTS sp_rebuild_station_data_by_imei $$

CREATE PROCEDURE sp_rebuild_station_data_by_imei(
    IN p_imei VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci,
    IN p_date_from DATE,
    IN p_date_to DATE
)
proc: BEGIN
    DECLARE v_report_table VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;
    DECLARE v_station_table VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;
    DECLARE v_from_utc DATETIME;
    DECLARE v_to_utc DATETIME;
    DECLARE v_source_count INT DEFAULT 0;
    DECLARE v_deleted_count INT DEFAULT 0;
    DECLARE v_inserted_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_gps;
        RESIGNAL;
    END;

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
      INTO v_report_table
    FROM tracker t
    WHERE t.imei COLLATE utf8_general_ci = TRIM(p_imei) COLLATE utf8_general_ci;

    IF v_report_table IS NULL OR NOT (
        v_report_table = 'data_report'
        OR v_report_table REGEXP '^data_report_[0-9]+$'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'imei not found or report_table is invalid';
    END IF;

    SET v_station_table = REPLACE(v_report_table, 'data_report', 'station_data');

    -- Input dates are Thailand local dates. GPS and station_data timestamps are UTC.
    SET v_from_utc = DATE_SUB(CAST(p_date_from AS DATETIME), INTERVAL 7 HOUR);
    SET v_to_utc = DATE_SUB(
        DATE_ADD(CAST(p_date_to AS DATETIME), INTERVAL 1 DAY),
        INTERVAL 7 HOUR
    );

    DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_gps;
    CREATE TEMPORARY TABLE tmp_station_rebuild_gps (
        gpsdata_id INT NOT NULL,
        data_date DATETIME NOT NULL,
        imei VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
        lat DECIMAL(12,8) NOT NULL,
        lng DECIMAL(12,8) NOT NULL,
        PRIMARY KEY (gpsdata_id),
        KEY idx_rebuild_imei_date (imei, data_date)
    ) ENGINE=InnoDB;

    SET @rebuild_imei = TRIM(p_imei);
    SET @rebuild_from_utc = v_from_utc;
    SET @rebuild_to_utc = v_to_utc;

    -- Read gps_data first so a point moved concurrently cannot fall between sources.
    INSERT IGNORE INTO tmp_station_rebuild_gps (
        gpsdata_id,
        data_date,
        imei,
        lat,
        lng
    )
    SELECT
        g.gpsdata_id,
        g.data_date,
        g.box_imei,
        ST_X(g.g_point),
        ST_Y(g.g_point)
    FROM gps_data g
    WHERE g.box_imei COLLATE utf8_general_ci = TRIM(p_imei) COLLATE utf8_general_ci
      AND g.data_date >= v_from_utc
      AND g.data_date < v_to_utc
      AND g.g_point IS NOT NULL
      AND ST_X(g.g_point) BETWEEN -90 AND 90
      AND ST_Y(g.g_point) BETWEEN -180 AND 180;

    -- Most historical points have already been moved to the IMEI's data_report table.
    SET @rebuild_report_sql = CONCAT(
        'INSERT IGNORE INTO tmp_station_rebuild_gps ',
        '(gpsdata_id, data_date, imei, lat, lng) ',
        'SELECT g.gpsdata_id, g.data_date, g.box_imei, ',
        '       ST_X(g.g_point), ST_Y(g.g_point) ',
        'FROM ', v_report_table, ' g ',
        'WHERE g.box_imei = ? ',
        '  AND g.data_date >= ? ',
        '  AND g.data_date < ? ',
        '  AND g.g_point IS NOT NULL ',
        '  AND ST_X(g.g_point) BETWEEN -90 AND 90 ',
        '  AND ST_Y(g.g_point) BETWEEN -180 AND 180'
    );

    PREPARE stmt_rebuild_report FROM @rebuild_report_sql;
    EXECUTE stmt_rebuild_report USING
        @rebuild_imei,
        @rebuild_from_utc,
        @rebuild_to_utc;
    DEALLOCATE PREPARE stmt_rebuild_report;

    SELECT COUNT(*)
      INTO v_source_count
    FROM tmp_station_rebuild_gps;

    IF v_source_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'no GPS data found for the requested IMEI and date range';
    END IF;

    START TRANSACTION;

    SET @rebuild_delete_sql = CONCAT(
        'DELETE sd ',
        'FROM ', v_station_table, ' sd ',
        'INNER JOIN tmp_station_rebuild_gps g ',
        '        ON g.gpsdata_id = sd.gpsdata_id ',
        'WHERE sd.imei = ?'
    );

    PREPARE stmt_rebuild_delete FROM @rebuild_delete_sql;
    EXECUTE stmt_rebuild_delete USING @rebuild_imei;
    SET v_deleted_count = ROW_COUNT();
    DEALLOCATE PREPARE stmt_rebuild_delete;

    -- data_report/gps_data store g_point as POINT(latitude longitude).
    -- stations store circle/polygon geometry as POINT(longitude latitude).
    SET @rebuild_insert_sql = CONCAT(
        'INSERT INTO ', v_station_table, ' ',
        '(data_date, imei, g_point, station_station_id, gpsdata_id) ',
        'SELECT DISTINCT ',
        '       g.data_date, ',
        '       g.imei, ',
        '       POINT(g.lat, g.lng), ',
        '       s.station_id, ',
        '       g.gpsdata_id ',
        'FROM tmp_station_rebuild_gps g ',
        'INNER JOIN customer_tracker ct ',
        '        ON ct.tracker_imei COLLATE utf8_general_ci = g.imei COLLATE utf8_general_ci ',
        'INNER JOIN stations s ',
        '        ON s.customer_customer_id = ct.customer_customer_id ',
        'WHERE ( ',
        '        ( ',
        '          (s.station_type = ''circle'' OR s.station_type IS NULL) ',
        '          AND fn_distance( ',
        '                g.lat, g.lng, ',
        '                ST_Y(s.station_point), ST_X(s.station_point) ',
        '              ) * 1000 <= s.radius ',
        '        ) ',
        '        OR ',
        '        ( ',
        '          s.station_type = ''polygon'' ',
        '          AND MBRContains(s.station_polygon, POINT(g.lng, g.lat)) = 1 ',
        '        ) ',
        '      )'
    );

    PREPARE stmt_rebuild_insert FROM @rebuild_insert_sql;
    EXECUTE stmt_rebuild_insert;
    SET v_inserted_count = ROW_COUNT();
    DEALLOCATE PREPARE stmt_rebuild_insert;

    COMMIT;

    DROP TEMPORARY TABLE IF EXISTS tmp_station_rebuild_gps;

    SELECT
        TRIM(p_imei) AS imei,
        p_date_from AS date_from,
        p_date_to AS date_to,
        v_report_table AS source_table,
        v_station_table AS station_table,
        v_source_count AS source_points,
        v_deleted_count AS deleted_station_points,
        v_inserted_count AS inserted_station_points;
END $$

DELIMITER ;

-- Rebuild raw station membership first, then rebuild the station summary.
-- CALL sp_rebuild_station_data_by_imei('864507034513654', '2026-08-24', '2026-08-24');
-- CALL sp_run_station_summary_report_by_imei('864507034513654', '2026-08-24', '2026-08-24');
