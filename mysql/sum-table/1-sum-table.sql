-- truncate table gps_sum_data;
-- truncate table gps_sum_status;
-- truncate table gps_sum_station;
-- truncate table gps_sum_log;

drop table if exists `gps_sum_data`;
drop table if exists `gps_sum_status`;
drop table if exists `gps_sum_station`;
drop table if exists `gps_sum_log`;
drop table if exists `gps_sum_station_log`;

CREATE TABLE IF NOT EXISTS gps_sum_data (
  imei varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  data_date date NOT NULL,
  run_time_s int NOT NULL DEFAULT 0,
  run_withid_time_s int NOT NULL DEFAULT 0,
  idle_time_s int NOT NULL DEFAULT 0,
  idle_over_5m_count int NOT NULL DEFAULT 0,
  park_time_s int NOT NULL DEFAULT 0,
  park_count int NOT NULL DEFAULT 0,
  distance_m int NOT NULL DEFAULT 0,
  distance_withid_m int NOT NULL DEFAULT 0,
  avg_speed_kph decimal(6,2) NOT NULL DEFAULT 0,
  max_speed_kph int NOT NULL DEFAULT 0,
  speed_over_count int NOT NULL DEFAULT 0,
  speed_over_cloud_count int NOT NULL DEFAULT 0,
  speed_over_device_count int NOT NULL DEFAULT 0,
  total_rows int NOT NULL default 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY (imei, data_date),
  KEY idx_date (data_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS gps_sum_status (
  id bigint NOT NULL AUTO_INCREMENT,
  imei varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  data_date date NOT NULL,
  gps_status varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  start_time datetime NOT NULL,
  end_time datetime NOT NULL,
  duration_s int NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY idx_imei_date (imei, data_date),
  KEY idx_date (data_date),
  KEY idx_imei_date_start (imei, data_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS gps_sum_station (
  id bigint NOT NULL AUTO_INCREMENT,
  imei varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  data_date date NOT NULL,
  station_id int NOT NULL,
  station_name VARCHAR(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL, 
  start_time datetime NOT NULL,
  end_time datetime DEFAULT NULL,
  duration_s int NOT NULL DEFAULT 0,
  distance_from_previous_m int NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY idx_imei_date (imei, data_date),
  KEY idx_station_date (station_id, data_date),
  KEY idx_imei_date_start (imei, data_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS gps_sum_log (
  id bigint NOT NULL AUTO_INCREMENT,
  process_date date NOT NULL,
  table_no int NOT NULL,
  start_time datetime NOT NULL,
  end_time datetime DEFAULT NULL,
  rows_processed int DEFAULT 0,
  status varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'RUNNING',
  error_message text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_date_table (process_date, table_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS gps_sum_station_log (
    id bigint NOT NULL AUTO_INCREMENT,
    process_date date NOT NULL,
    table_no int NOT NULL,
    start_time datetime NOT NULL,
    end_time datetime DEFAULT NULL,
    rows_processed int DEFAULT 0,
    status varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'RUNNING',
    error_message text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_date_table (process_date, table_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


-- ALTER TABLE gps_sum_data CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
-- ALTER TABLE gps_sum_status CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
-- ALTER TABLE gps_sum_station CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
-- ALTER TABLE gps_sum_log CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
-- ALTER TABLE gps_sum_station_log CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
