ALTER TABLE gps_sum_data
    ADD COLUMN speed_over_cloud_count INT NOT NULL DEFAULT 0 AFTER speed_over_count,
    ADD COLUMN speed_over_device_count INT NOT NULL DEFAULT 0 AFTER speed_over_cloud_count;

-- Preserve the existing cloud-only count when upgrading historical rows.
UPDATE gps_sum_data
SET speed_over_cloud_count = speed_over_count;
