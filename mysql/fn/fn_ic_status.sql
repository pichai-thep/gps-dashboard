DROP FUNCTION IF EXISTS fn_ic_status;

DELIMITER $$

CREATE FUNCTION fn_ic_status(
    p_data_date DATETIME,
    p_received_date DATETIME,
    p_gps_status VARCHAR(5),
    p_state VARCHAR(255),
    p_input_acc TINYINT,
    p_engine_volt DECIMAL(5,2),
    p_ext_power DECIMAL(5,2),
    p_speed INT
)
RETURNS TINYINT
DETERMINISTIC
BEGIN
    /*
      0 = null
      1 = off-line
      2 = gps-v
      3 = park
      4 = acc-on
      5 = start
      6 = run
    */

    IF p_data_date IS NULL THEN
        RETURN 0;
    END IF;

    IF TIMESTAMPDIFF(MINUTE, p_received_date, NOW()) > 30 THEN
        RETURN 1;
    END IF;

    IF UPPER(IFNULL(p_gps_status, '')) <> 'A' THEN
        RETURN 2;
    END IF;

    IF IF(
        CHAR_LENGTH(IFNULL(p_state, '')) > 0,
        SUBSTR(p_state, IFNULL(p_input_acc, 1), 1),
        '0'
    ) <> '1' THEN
        RETURN 3;
    END IF;

    IF IFNULL(p_speed, 0) > 0 THEN
        RETURN 6;
    END IF;

    IF p_engine_volt IS NOT NULL THEN
        IF IFNULL(p_ext_power, 0) >= p_engine_volt THEN
            RETURN 5;
        ELSE
            RETURN 4;
        END IF;
    END IF;

    RETURN 5;
END$$

DELIMITER ;