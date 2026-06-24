DELIMITER $$

DROP FUNCTION IF EXISTS fn_fuel_percent $$

CREATE FUNCTION fn_fuel_percent(
    p_input_fuel     TINYINT,
    p_tracker_model  VARCHAR(15),
    p_ad             VARCHAR(24),
    p_min_volt       DECIMAL(5,2),
    p_max_volt       DECIMAL(5,2),
    p_fuel_reverse   TINYINT
)
RETURNS DECIMAL(5,2)
DETERMINISTIC
BEGIN
    DECLARE v_fuel DECIMAL(5,2);

    IF IFNULL(p_input_fuel, -1) <= 0
		OR ifnull(p_min_volt,-1) <= 0
        OR ifnull(p_max_volt,-1) <= 0
		OR p_ad IS NULL
		OR LENGTH(TRIM(p_ad)) = 0 THEN
        RETURN NULL;
    END IF;

    CASE p_tracker_model

        WHEN 'T1' THEN
            SET v_fuel = fn_fuel_T1(
                p_ad,
                p_input_fuel,
                -1,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );
            
		WHEN 'T333' THEN
            SET v_fuel = fn_fuel_T1(
                p_ad,
                p_input_fuel,
                -1,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'TopFlyTech' THEN
            SET v_fuel = fn_fuel_topfly(
                p_ad,
                p_max_volt,
                p_min_volt
            );

        WHEN 'Totem-107-3g' THEN
            SET v_fuel = fn_fuel_totem(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'Totem-107-4g' THEN
            SET v_fuel = fn_fuel_totem(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'Totem-09' THEN
            SET v_fuel = fn_fuel_totem(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );
            
		WHEN 'Totem-109' THEN
            SET v_fuel = fn_fuel_totem(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );
		
        WHEN 'Totem-109-3G' THEN
            SET v_fuel = fn_fuel_totem(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );
		
        WHEN 'Totem-109-4G' THEN
            SET v_fuel = fn_fuel_totem(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'Ruptela' THEN
            SET v_fuel = fn_fuel_ruptela(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'Concox' THEN
            SET v_fuel = fn_fuel_concox(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'JC400' THEN
            SET v_fuel = fn_fuel_concox(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        WHEN 'FiFoTrack' THEN
            SET v_fuel = fn_fuel_fifotrack(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );
            
		WHEN 'iStartek' THEN
            SET v_fuel = fn_fuel_istartek(
                p_ad,
                p_max_volt,
                p_min_volt,
                p_fuel_reverse
            );

        ELSE
            SET v_fuel = NULL;

    END CASE;

    RETURN v_fuel;
END $$

DELIMITER ;