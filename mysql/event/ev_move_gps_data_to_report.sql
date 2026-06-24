DELIMITER $$

DROP EVENT IF EXISTS ev_move_gps_data_to_report $$

CREATE EVENT ev_move_gps_data_to_report
ON SCHEDULE EVERY 10 MINUTE
DO
BEGIN
    CALL sp_move_gps_data_to_report(50000);
END $$

DELIMITER ;