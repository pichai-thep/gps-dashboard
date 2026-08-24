ALTER TABLE `gps_sum_data`
  ADD COLUMN `idle_over_5m_count` int NOT NULL DEFAULT 0 AFTER `idle_time_s`,
  ADD COLUMN `park_count` int NOT NULL DEFAULT 0 AFTER `park_time_s`;
