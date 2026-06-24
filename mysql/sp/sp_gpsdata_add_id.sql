drop procedure if exists `sp_gpsdata_add_id`;
Delimiter $$

CREATE PROCEDURE `sp_gpsdata_add_id`(
	`p_data_date` datetime,
	`p_imei` varchar(20),
	`p_lat` decimal(12,8),
	`p_lng` decimal(12,8),
	`p_gps_status` char(1),
	`p_speed` int ,
	`p_heading` decimal(6,2) ,
	`p_state` varchar(32) ,
	`p_ad` varchar(40),
	`p_ip` varchar(23) ,
	`p_event_code` varchar(3) ,
	`p_temp` varchar(10) ,
	`p_mileage` int ,
	`p_received_date` datetime,
	`p_lat0` decimal(12,8),
	`p_lng0` decimal(12,8),
	`p_alt` smallint,
	`p_hdop` decimal(3,1),
	`p_num_sats` smallint,
	`p_ext_power` decimal(5,2),
	`p_ext_power_status` tinyint,
	`p_track1` varchar(50),
	`p_track2` varchar(50),
	`p_track3` varchar(90),
	`p_serial_no` int,
	`p_gsm_signal` tinyint,
    `p_rs232_data` varchar(10),
    out `p_gpsdata_id` int
)
proc: begin

	declare var_insert_id int;
	declare var_point, old_point point;
    declare var_prov_code varchar(2);
    declare var_amp_code varchar(4);
	declare var_address varchar(200);
    declare var_distance decimal(10,3);
        
    if (p_lat=0) or (p_lng=0) then
		set old_point = (select g_point from gps_data where box_imei=p_imei and st_x(g_point)<>0 and st_y(g_point)<>0 order by data_date desc limit 0,1);
        set var_point = old_point;
    else
		set var_point = (select Point(p_lat, p_lng));
    end if;
                                     
    
	INSERT INTO `gps_data`
							(
							`data_date`,
							`box_imei`,
							`g_point`,
                            `address`,
							`gps_status`,
							`speed`,
							`heading`,
							`state`,
							`ad`,
							`ip`,
							`event_code`,
							`temp`,
							`mileage`,
							`received_date`,
                            `prov_code`,
                            `amp_code`,
                            
                            `alt`,
                            `hdop`,
                            `num_sats`,
                            `ext_power`,
                            `ext_power_status`,
                            `track1`,
                            `track2`,
                            `track3`,
                            `serial_no`,
                            `gsm_signal`,
                            `rs232_data`
                                                        
							)
		VALUES(
							  p_data_date,
							  p_imei,
							  var_point,
                              var_address,
							  p_gps_status,
							  p_speed,
							  p_heading,
							  p_state,
							  p_ad,
							  p_ip,
							  p_event_code,
							  p_temp,
							  p_mileage,
							  p_received_date,
                              var_prov_code,
                              var_amp_code,
                              
                              p_alt,
                              p_hdop,
                              p_num_sats,
                              p_ext_power,
                              p_ext_power_status,
                              p_track1,
                              p_track2,
                              p_track3,
                              p_serial_no,
                              p_gsm_signal,
                              p_rs232_data
						);

    if (p_lat is not null) and (p_lng is not null)  then		
        set p_gpsdata_id = last_insert_id();    
		call sp_station_in2(p_imei, p_data_date, p_lat, p_lng, p_gpsdata_id);                
	end if;
                        
end