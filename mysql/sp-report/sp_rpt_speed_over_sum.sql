DROP PROCEDURE IF EXISTS sp_rpt_speed_over_sum;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_rpt_speed_over_sum`(
    IN _group      INT,
    IN _login      VARCHAR(20),
    IN _over_type  VARCHAR(6),
    IN _imei       VARCHAR(20),
    IN _date1      VARCHAR(10),   -- 'YYYY-MM-DD'
    IN _date2      VARCHAR(10)    -- 'YYYY-MM-DD'
)
proc: BEGIN
    DECLARE v_start DATETIME;
    DECLARE v_end   DATETIME;

    /* แปลงช่วงเวลาแบบครึ่งเปิด [date1 00:00, date2+1 00:00) และชดเชย +7 ชม. ด้วยการเปรียบเทียบที่ฝั่ง SELECT */
    SET v_start = STR_TO_DATE(_date1, '%Y-%m-%d');
    SET v_end   = DATE_ADD(STR_TO_DATE(_date2, '%Y-%m-%d'), INTERVAL 1 DAY);

    /* เตรียม staging ชุดข้อมูลที่ผ่านเงื่อนไขทั้งหมด */
    DROP TEMPORARY TABLE IF EXISTS tmp_over_speed;
    CREATE TEMPORARY TABLE tmp_over_speed(
        id            INT,
        imei          VARCHAR(20),
        plate_no      VARCHAR(50),
        data_date     DATETIME,
        speed_limited MEDIUMINT,
        speed         MEDIUMINT,
        lat           DECIMAL(12,8),
        lon           DECIMAL(12,8)
    ) ENGINE=MyISAM;

    INSERT INTO tmp_over_speed
    SELECT  sp.id,
            sp.imei,
            t.plate_no,
            DATE_ADD(sp.event_time, INTERVAL 7 HOUR)          AS data_date,
            t.speed_limited,
            sp.speed,
            sp.lat,
            sp.lng
    FROM gps_speed_over sp
    INNER JOIN tracker t              ON sp.imei = t.imei
    INNER JOIN user_tracker ut        ON t.imei = ut.tracker_imei
    INNER JOIN user u                 ON ut.user_user_id = u.user_id
    LEFT  JOIN customer_group_tracker cgt ON t.imei = cgt.imei
    LEFT  JOIN customer_group cg          ON cgt.customer_group_id = cg.customer_group_id
    WHERE
        /* กรองตามกลุ่ม (optional) */
        IF(NULLIF(_group,-1) IS NOT NULL, cgt.customer_group_id, -1)
            = IF(NULLIF(_group,-1) IS NOT NULL, _group, -1)
        /* กรองประเภท overspeed (optional) */
        AND sp.over_type = COALESCE(NULLIF(_over_type,''), sp.over_type)
        /* กรองตามผู้ใช้ (optional) */
        AND u.login = COALESCE(NULLIF(_login,''), u.login)
        /* กรอง IMEI (optional) */
        AND sp.imei = COALESCE(NULLIF(_imei,''), sp.imei)
        /* กรองช่วงเวลา (เทียบหลังชดเชย +7 ชม.) */
        AND DATE(DATE_ADD(sp.event_time, INTERVAL 7 HOUR)) BETWEEN _date1 AND _date2
        /* ความแม่นยำสัญญาณ (ตามฟังก์ชันของคุณ) */
        AND fn_is_accuracy(t.tracker_model, sp.hdop, sp.num_sats) = 1;

    /* ---------- สรุปต่อ IMEI (รองรับทั้งกรณีระบุ/ไม่ระบุ IMEI) ---------- */

    /* A: aggregate หลักต่อ IMEI */
    /*   - จำนวนเหตุการณ์, จำนวนวัน, ความเร็วสูงสุด */
    DROP TEMPORARY TABLE IF EXISTS tmp_agg;
    CREATE TEMPORARY TABLE tmp_agg ENGINE=MyISAM
    AS
    SELECT
        imei,
        MAX(plate_no) AS plate_no,               -- plate_no ควรคงที่ต่อ IMEI
        max(speed_limited) as speed_limited,
--         COUNT(DISTINCT DATE(data_date)) AS total_events,
--         COUNT(DISTINCT DATE(data_date)) AS total_days,
        COUNT(distinct id) AS total_events,
        COUNT(DISTINCT DATE(data_date)) AS total_days,        
        MAX(speed)    AS max_speed
    FROM tmp_over_speed
    WHERE imei = COALESCE(NULLIF(_imei,''), imei)
    GROUP BY imei;

    /* B1: หาเวลาล่าสุดของเหตุการณ์ที่มีความเร็ว = max_speed ต่อ IMEI */
    DROP TEMPORARY TABLE IF EXISTS tmp_max_time;
    CREATE TEMPORARY TABLE tmp_max_time ENGINE=MyISAM
    AS
    SELECT s.imei, MAX(s.data_date) AS max_dt
    FROM tmp_over_speed s
    JOIN tmp_agg a
      ON a.imei = s.imei
     AND s.speed = a.max_speed
    GROUP BY s.imei;

    /* B2: กรณีเวลาเท่ากัน ให้เลือก id สูงสุดต่อ IMEI เพื่อได้แถวเดียวชัดเจน */
    DROP TEMPORARY TABLE IF EXISTS tmp_max_row;
    CREATE TEMPORARY TABLE tmp_max_row ENGINE=MyISAM
    AS
    SELECT s.imei, MAX(s.id) AS max_id
    FROM tmp_over_speed s
    JOIN tmp_agg a       ON a.imei = s.imei AND s.speed = a.max_speed
    JOIN tmp_max_time t2 ON t2.imei = s.imei AND s.data_date = t2.max_dt
    GROUP BY s.imei;

    /* C: แถวเหตุการณ์จริงของ “ความเร็วสูงสุด” ต่อ IMEI */
    DROP TEMPORARY TABLE IF EXISTS tmp_pick;
    CREATE TEMPORARY TABLE tmp_pick ENGINE=MyISAM
    AS
    SELECT s.*
    FROM tmp_over_speed s
    JOIN tmp_max_row r ON r.imei = s.imei AND r.max_id = s.id;

    /* Final: รวมผลลัพธ์ */
    SELECT 
		DISTINCT 
        a.imei,
        a.plate_no,
        a.speed_limited,
        a.max_speed,
        a.total_events,
        a.total_days,
        DATE(p.data_date) AS max_speed_date,
        TIME(p.data_date) AS max_speed_time,
        p.lat             AS max_speed_lat,
        p.lon             AS max_speed_lng,
        fn_geo_tambon_shp(p.lat, p.lon, 1) AS address
    FROM tmp_agg a
    JOIN tmp_pick p ON p.imei = a.imei
    ORDER BY a.total_events DESC, a.imei;

END $$
DELIMITER ;
