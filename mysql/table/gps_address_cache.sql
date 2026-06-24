CREATE TABLE gps_address_cache (
    box_imei varchar(20) NOT NULL,
    address varchar(255) DEFAULT NULL,
    tam_code varchar(6) DEFAULT NULL,
    tam_name_th varchar(100) DEFAULT NULL,
    amp_code varchar(4) DEFAULT NULL,
    amp_name_th varchar(100) DEFAULT NULL,
    prov_code varchar(2) DEFAULT NULL,
    prov_name_th varchar(100) DEFAULT NULL,
    lat decimal(11,8) DEFAULT NULL,
    lng decimal(11,8) DEFAULT NULL,
    gps_time datetime DEFAULT NULL,
    updated_at datetime DEFAULT NULL,
    PRIMARY KEY (box_imei),
    KEY idx_updated_at (updated_at),
    KEY idx_province (prov_code),
    KEY idx_amp (amp_code)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb3;

drop table gps_address_cache;