ALTER TABLE `gps_sum_data`
  ADD COLUMN `distance_withid_m` int NOT NULL DEFAULT 0 AFTER `distance_m`;
