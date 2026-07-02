ALTER TABLE tracker_mileage ENGINE=InnoDB;

ALTER TABLE tracker_mileage
    ADD CONSTRAINT fk_tracker_mileage_tracker
        FOREIGN KEY (imei) REFERENCES tracker(imei)
            ON DELETE CASCADE ON UPDATE CASCADE;