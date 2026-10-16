DROP PROCEDURE IF EXISTS `sp_rpt_fuel_abnormal`;
DELIMITER $$

CREATE PROCEDURE `sp_rpt_fuel_abnormal`(
    IN _group INT,
    IN _imei VARCHAR(20),
    IN _date1 VARCHAR(10),
    IN _date2 VARCHAR(10),
    IN _login VARCHAR(20)
)
proc: BEGIN
    SELECT f.id, f.imei, t.plate_no, t.tracker_model,
           DATE_ADD(f.data_date, INTERVAL 7 HOUR) AS data_date,
           f.fuel_avg, f.fuel_last, f.fuel_max_diff,
           ST_X(f.g_point) AS lat, ST_Y(f.g_point) AS lon,
           f.created_time
    FROM abnormal_fuel f
    INNER JOIN tracker t ON f.imei = t.imei
    INNER JOIN customer_tracker ct ON f.imei = ct.tracker_imei
    INNER JOIN customer c ON ct.customer_customer_id = c.customer_id
    INNER JOIN customer_user cu ON c.customer_id = cu.customer_customer_id
    INNER JOIN `user` u ON cu.user_user_id = u.user_id
    LEFT JOIN customer_group_tracker cgt ON f.imei = cgt.imei
    LEFT JOIN customer_group cg ON cgt.customer_group_id = cg.customer_group_id
    WHERE u.login = COALESCE(_login, u.login)
      AND IF(NULLIF(_group, -1) IS NOT NULL, cgt.customer_group_id, -1)
          = IF(NULLIF(_group, -1) IS NOT NULL, _group, -1)
      AND f.imei = COALESCE(NULLIF(_imei, ''), f.imei)
      AND (DATE(DATE_ADD(f.data_date, INTERVAL 7 HOUR)) >= DATE(_date1) OR _date1 IS NULL)
      AND (DATE(DATE_ADD(f.data_date, INTERVAL 7 HOUR)) <= DATE(_date2) OR _date2 IS NULL)
    ORDER BY f.id DESC;
END$$

DELIMITER ;
