CREATE INDEX idx_user_login
    ON user (login);

CREATE INDEX idx_user_tracker_imei_user
    ON user_tracker (tracker_imei, user_user_id);

CREATE INDEX idx_gps_sum_date_imei
    ON gps_sum_data (data_date, imei);
    
CREATE INDEX idx_gps_sum_status_date_imei
    ON gps_sum_status (data_date, imei);
    
CREATE INDEX idx_gps_sum_station_date_imei_station
    ON gps_sum_station (data_date, imei, station_id);

CREATE INDEX idx_customer_tracker_imei
    ON customer_tracker (tracker_imei);    

CREATE INDEX idx_tracker_imei
    ON tracker (imei);    
    
ALTER TABLE user_tracker
ADD UNIQUE KEY uq_user_tracker_user_imei (
    user_user_id,
    tracker_imei
);

-- customer: storetw
-- Error Code: 1062. Duplicate entry '1026-862771072741021' for key 'uq_user_tracker_user_imei'
-- Error Code: 1062. Duplicate entry '1026-862771072740650' for key 'uq_user_tracker_user_imei'
-- Error Code: 1062. Duplicate entry '1026-862771072739934' for key 'uq_user_tracker_user_imei'
-- Error Code: 1062. Duplicate entry '1045-862771072748455' for key 'uq_user_tracker_user_imei'
-- Error Code: 1062. Duplicate entry '745-864606048486939' for key 'uq_user_tracker_user_imei'
-- Error Code: 1062. Duplicate entry '1077-862608080110130' for key 'uq_user_tracker_user_imei'



