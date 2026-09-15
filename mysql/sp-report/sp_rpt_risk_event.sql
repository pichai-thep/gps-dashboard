-- Install on each GPS database containing dlt_risk_event before deploying the API.
DROP PROCEDURE IF EXISTS sp_rpt_risk_event;
DELIMITER $$

CREATE PROCEDURE sp_rpt_risk_event(
    IN _group INT,
    IN _login VARCHAR(255),
    IN _risk_obj VARCHAR(10),
    IN _risk_type VARCHAR(10),
    IN _datetime_from DATETIME,
    IN _datetime_to DATETIME
)
BEGIN
    DECLARE v_start BIGINT;
    DECLARE v_end BIGINT;

    -- Inputs are Thailand local time; event_time stores Unix milliseconds.
    -- Epoch arithmetic avoids dependence on the MySQL session time_zone.
    SET v_start = TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', DATE_SUB(_datetime_from, INTERVAL 7 HOUR)) * 1000;
    SET v_end = (TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', DATE_SUB(_datetime_to, INTERVAL 7 HOUR)) + 1) * 1000;

    SELECT e.id, e.imei, t.plate_no, e.risk_obj, e.risk_type,
           e.ref_id, e.risk_name, e.lat, e.lng, e.distance_meter,
           DATE_ADD(TIMESTAMPADD(SECOND, e.event_time DIV 1000, '1970-01-01 00:00:00'), INTERVAL 7 HOUR) AS event_time,
           e.created_at
    FROM dlt_risk_event e
    JOIN tracker t ON t.imei = e.imei
    WHERE e.event_time >= v_start
      AND e.event_time < v_end
      AND (_risk_obj = '' OR e.risk_obj = _risk_obj)
      AND (_risk_type = '' OR e.risk_type = _risk_type)
      AND EXISTS (
          SELECT 1 FROM user_tracker ut
          JOIN user u ON u.user_id = ut.user_user_id
          WHERE ut.tracker_imei = e.imei
            AND TRIM(u.login) = TRIM(_login)
      )
      AND (_group = -1 OR EXISTS (
          SELECT 1 FROM customer_group_tracker cgt
          WHERE cgt.imei = e.imei
            AND cgt.customer_group_id = _group
      ))
    ORDER BY e.event_time, e.id;
END$$
DELIMITER ;
