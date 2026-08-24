ALTER TABLE gps_sum_data
    ADD COLUMN avg_speed_kph DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER distance_withid_m,
    ADD COLUMN max_speed_kph INT NOT NULL DEFAULT 0 AFTER avg_speed_kph,
    ADD COLUMN speed_over_count INT NOT NULL DEFAULT 0 AFTER max_speed_kph;
