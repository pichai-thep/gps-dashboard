DELIMITER $$

DROP PROCEDURE IF EXISTS sp_move_gps_data_to_report$$

CREATE PROCEDURE `sp_move_gps_data_to_report`(
    IN p_limit INT
)
proc: BEGIN

    DECLARE done INT DEFAULT FALSE;
    DECLARE v_report_table varchar(15);
    DECLARE v_sql LONGTEXT;

    DECLARE cur_report CURSOR FOR
        SELECT DISTINCT report_table
        FROM tmp_move_gpsdata;

    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET done = TRUE;

    DROP TEMPORARY TABLE IF EXISTS tmp_keep_gpsdata;
    CREATE TEMPORARY TABLE tmp_keep_gpsdata (
        gpsdata_id INT NOT NULL PRIMARY KEY
    ) ENGINE=MEMORY;

    INSERT INTO tmp_keep_gpsdata (gpsdata_id)
    SELECT MAX(g.gpsdata_id)
    FROM gps_data g
    JOIN tracker t ON t.imei = g.box_imei
    WHERE g.g_point IS NOT NULL
      AND FIND_IN_SET(
            g.event_code,
            CASE
                WHEN t.tracker_model IN ('T1','T333') THEN '35,3,11'
                WHEN t.tracker_model LIKE 'Totem%' THEN 'AA,21,22,02,03'
                WHEN t.tracker_model = 'Ruptela' THEN '7,8'
                WHEN t.tracker_model = 'Concox' THEN '12,16'
                WHEN t.tracker_model = 'FiFoTrack' THEN 'A01,4,5'
                WHEN t.tracker_model = 'iStartek' THEN '0'
                ELSE ''
            END
        ) > 0
    GROUP BY g.box_imei;
    
    DROP TEMPORARY TABLE IF EXISTS tmp_move_gpsdata;
    CREATE TEMPORARY TABLE tmp_move_gpsdata (
        gpsdata_id INT NOT NULL PRIMARY KEY,
        report_table varchar(15) NOT NULL
    ) ENGINE=MEMORY;

    INSERT INTO tmp_move_gpsdata (gpsdata_id, report_table)
    SELECT
        g.gpsdata_id,
        t.report_table
    FROM gps_data g
		JOIN tracker t ON t.imei = g.box_imei
		LEFT JOIN tmp_keep_gpsdata k ON k.gpsdata_id = g.gpsdata_id
    WHERE t.report_table is not null
		AND k.gpsdata_id IS NULL
		AND (
            t.export_to IS NULL
            OR t.export_to = ''
            OR IFNULL(t.export_to_active,0) <> 1
            OR IFNULL(g.export_to_flag,'0') = '1'
            OR g.data_date < DATE_SUB(NOW(), INTERVAL 60 MINUTE)
          )
    ORDER BY g.gpsdata_id
    LIMIT p_limit;
    
    DROP TEMPORARY TABLE IF EXISTS tmp_inserted_gpsdata;
    CREATE TEMPORARY TABLE tmp_inserted_gpsdata (
        gpsdata_id INT NOT NULL PRIMARY KEY
    ) ENGINE=MEMORY;

    OPEN cur_report;

    read_loop:
    LOOP
        FETCH cur_report INTO v_report_table;

        IF done THEN
            LEAVE read_loop;
        END IF;

        SET v_sql = CONCAT(
        'INSERT  IGNORE INTO ', v_report_table, '
        (
            gpsdata_id, data_date, box_imei, g_point, address,
            gps_status, speed, heading, state, ad, ip, event_code,
            temp, mileage, received_date, prov_code, amp_code,
            alt, hdop, num_sats, ext_power_status, ext_power,
            synch_status, track1, track2, track3, serial_no,
            gsm_signal, rs232_data
        )
        SELECT
            g.gpsdata_id, g.data_date, g.box_imei, g.g_point, g.address,
            g.gps_status, g.speed, g.heading, g.state, g.ad, g.ip, g.event_code,
            g.temp, g.mileage, g.received_date, g.prov_code, LEFT(g.amp_code, 4),
            g.alt, g.hdop, g.num_sats, g.ext_power_status, g.ext_power,
            g.synch_status, g.track1, g.track2, g.track3, g.serial_no,
            g.gsm_signal, g.rs232_data
        FROM gps_data g
        JOIN tmp_move_gpsdata m ON m.gpsdata_id = g.gpsdata_id
        WHERE m.report_table = ''', v_report_table, ''''
        );

		SET @v_sql = v_sql;        
        PREPARE stmt FROM @v_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET v_sql = CONCAT(
        'INSERT IGNORE INTO tmp_inserted_gpsdata (gpsdata_id)
         SELECT m.gpsdata_id
         FROM tmp_move_gpsdata m
         JOIN ', v_report_table, ' r
            ON r.gpsdata_id = m.gpsdata_id
         WHERE m.report_table = ''', v_report_table, ''''
        );

		SET @v_sql = v_sql;
        PREPARE stmt FROM @v_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

    END LOOP;

    CLOSE cur_report;

    DELETE g
    FROM gps_data g
    JOIN tmp_inserted_gpsdata i ON i.gpsdata_id = g.gpsdata_id;

    DROP TEMPORARY TABLE IF EXISTS tmp_keep_gpsdata;
    DROP TEMPORARY TABLE IF EXISTS tmp_move_gpsdata;
    DROP TEMPORARY TABLE IF EXISTS tmp_inserted_gpsdata;

END$$

DELIMITER ;