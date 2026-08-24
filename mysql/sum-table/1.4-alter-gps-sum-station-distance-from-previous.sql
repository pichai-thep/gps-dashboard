ALTER TABLE gps_sum_station
    ADD COLUMN distance_from_previous_m INT NOT NULL DEFAULT 0
    AFTER duration_s;
