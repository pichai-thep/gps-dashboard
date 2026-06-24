DROP FUNCTION IF EXISTS fn_geo_tambon_shp;

DELIMITER $$

CREATE FUNCTION fn_geo_tambon_shp(
    p_lat DECIMAL(11,8),
    p_lon DECIMAL(11,8),
    p_idx INT
) RETURNS VARCHAR(200) CHARSET utf8mb3
    READS SQL DATA
    NOT DETERMINISTIC
BEGIN
    DECLARE p_address VARCHAR(200) DEFAULT '';
    DECLARE p POINT;

    IF p_idx = -1 THEN
        RETURN '';
    END IF;

    SET p = ST_SRID(POINT(p_lon, p_lat), 0);

    SELECT CONCAT(tam_nam_t, '/', amphoe_t, '/', prov_nam_t)
    INTO p_address
    FROM shp_tambon
    WHERE MBRContains(geom, p)
      AND ST_Contains(geom, p)
    LIMIT 1;

    IF p_address IS NULL OR p_address = '' THEN
        SELECT CONCAT(tam_nam_t, '/', amphoe_t, '/', prov_nam_t)
        INTO p_address
        FROM shp_tambon
        WHERE MBRContains(geom, p)
        LIMIT 1;
    END IF;

    RETURN IFNULL(p_address, '');
END$$

DELIMITER ;