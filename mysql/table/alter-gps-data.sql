SELECT COUNT(*)
FROM gps_data
WHERE MONTH(data_date)=0 OR DAY(data_date)=0;

DELETE from gps_data WHERE MONTH(data_date)=0 OR DAY(data_date)=0;

ALTER TABLE gps_data DROP COLUMN address;
ALTER TABLE gps_data DROP COLUMN amp_code;
ALTER TABLE gps_data DROP COLUMN prov_code;

ALTER TABLE gps_data
    ADD COLUMN address VARCHAR(255) NULL,
    ADD COLUMN tam_code VARCHAR(6) NULL,
    ADD COLUMN amp_code VARCHAR(6) NULL,
    ADD COLUMN prov_code VARCHAR(2) NULL,
    ADD COLUMN address_resolved_at DATETIME NULL,
    ADD INDEX idx_address_resolved (address_resolved_at),
    ADD INDEX idx_gpsdata_id (gpsdata_id);