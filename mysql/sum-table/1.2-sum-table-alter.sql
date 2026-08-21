ALTER TABLE `gps_sum_data`
  ADD COLUMN `run_withid_time_s` int NOT NULL DEFAULT 0 AFTER `run_time_s`;
