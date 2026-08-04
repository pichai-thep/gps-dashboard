DROP PROCEDURE IF EXISTS sp_rpt_event;
DELIMITER $$

CREATE PROCEDURE sp_rpt_event(
    IN _group INT,
    IN _login VARCHAR(20),
    IN _imei VARCHAR(20),
    IN _event_type VARCHAR(30),
    IN _date1 DATE,
    IN _date2 DATE
)
BEGIN

    DECLARE v_start DATETIME;
    DECLARE v_end DATETIME;
    
    SET v_start = CONCAT(_date1, ' 00:00:00');
	SET v_end   = CONCAT(_date2, ' 23:59:59');

    -- ถ้ามีข้อมูลใน gps_event
    IF EXISTS (
        SELECT 1
        FROM gps_event
--         WHERE event_time BETWEEN v_start AND v_end
        -- filter ด้วย UTC
		WHERE DATE_ADD(event_time, INTERVAL 7 HOUR) >= v_start
		  AND DATE_ADD(event_time, INTERVAL 7 HOUR) < DATE_ADD(v_end, INTERVAL 1 SECOND)
        LIMIT 1
    ) THEN
    		
		SELECT  tv.id, tv.imei, t.plate_no, tv.event_type,
                DATE_ADD(tv.event_time, INTERVAL 7 HOUR) AS event_time,
                tv.driver_id, tv.lat, tv.lng, tv.speed,
                tv.event_count, tv.created_date,                
                fn_geo_tambon_shp(tv.lat, tv.lng, 1) as address
        FROM gps_event tv
        JOIN tracker t ON tv.imei = t.imei
        JOIN user_tracker ut ON t.imei = ut.tracker_imei
        JOIN user u ON ut.user_user_id = u.user_id
        LEFT JOIN customer_group_tracker cgt ON t.imei = cgt.imei
        -- WHERE DATE_ADD(tv.event_time, INTERVAL 7 HOUR) BETWEEN v_start AND v_end
		WHERE		DATE_ADD(tv.event_time, INTERVAL 7 HOUR) >= v_start
				AND DATE_ADD(tv.event_time, INTERVAL 7 HOUR) < DATE_ADD(v_end, INTERVAL 1 SECOND)
          AND tv.imei = COALESCE(NULLIF(_imei,''), tv.imei)
          AND u.login = COALESCE(NULLIF(_login,''), u.login)
          AND tv.event_type = COALESCE(NULLIF(_event_type,''), tv.event_type)
          AND (_group = -1 OR cgt.customer_group_id = _group)
        ORDER BY tv.event_time;

    ELSE

        SELECT  tv.id, tv.imei, t.plate_no, tv.event_type,
                DATE_ADD(tv.event_time, INTERVAL 7 HOUR) AS event_time,
                tv.driver_id, tv.lat, tv.lng, tv.speed,
                tv.event_count, tv.created_date,
                fn_geo_tambon_shp(tv.lat, tv.lng, 1) as address
        FROM gps_event_report tv
        JOIN tracker t ON tv.imei = t.imei
        JOIN user_tracker ut ON t.imei = ut.tracker_imei
        JOIN user u ON ut.user_user_id = u.user_id
        LEFT JOIN customer_group_tracker cgt ON t.imei = cgt.imei
        WHERE DATE_ADD(tv.event_time, INTERVAL 7 HOUR) BETWEEN v_start AND v_end
          AND tv.imei = COALESCE(NULLIF(_imei,''), tv.imei)
          AND u.login = COALESCE(NULLIF(_login,''), u.login)
          AND tv.event_type = COALESCE(NULLIF(_event_type,''), tv.event_type)
          AND (_group = -1 OR cgt.customer_group_id = _group)
        ORDER BY tv.event_time;

    END IF;

END$$
DELIMITER ;
