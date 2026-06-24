Drop procedure if exists sp_myfleet_fcm_devices;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE  PROCEDURE `sp_myfleet_fcm_devices`(
  `_imei` varchar(20),
  `_msg_type` varchar(25),   
  `_msg` varchar(255)
  )
proc: begin
  

    DROP TEMPORARY TABLE IF EXISTS tmp_alert;
		create temporary table tmp_alert(
			login varchar(20),
			alert_type varchar(28)
		)engine myisam;

    DROP TEMPORARY TABLE IF EXISTS tmp_user;
		create temporary table tmp_user(
			login varchar(20)
		)engine myisam;        
  
	insert into tmp_user
		select  u.login
		from	tracker t 
-- 					inner join customer_tracker ct on t.imei=ct.tracker_imei
-- 					inner join customer c on ct.customer_customer_id=c.customer_id
-- 					inner join customer_user cu on c.customer_id=cu.customer_customer_id
                    inner join user_tracker ut on t.imei=ut.tracker_imei
                    inner join user u on ut.user_user_id=u.user_id
					
		where t.imei=_imei;
    
    insert into tmp_alert select  u.login, 'abnormal_fuel_alert' from user u inner join tmp_user tu on u.login=tu.login where abnormal_fuel_alert=1;                                                        
	insert into tmp_alert select  u.login, 'abnormal_gps_alert' from user u inner join tmp_user tu on u.login=tu.login where abnormal_gps_alert=1;                                                        
	
    insert into tmp_alert select  u.login, 'speed_over_alert'   from user u inner join tmp_user tu on u.login=tu.login where speed_over_alert=1;    
    insert into tmp_alert select  u.login, 'speed_over_device_alert'   from user u inner join tmp_user tu on u.login=tu.login where speed_over_device_alert=1;
    insert into tmp_alert select  u.login, 'speed_over_cloud_alert'   from user u inner join tmp_user tu on u.login=tu.login where speed_over_cloud_alert=1;
    
	insert into tmp_alert select  u.login, 'input1_on_alert' from user u inner join tmp_user tu on u.login=tu.login where input1_on_alert=1;
	insert into tmp_alert select  u.login, 'input1_off_alert' from user u inner join tmp_user tu on u.login=tu.login where input1_off_alert=1;
    
	insert into tmp_alert select  u.login, 'input2_on_alert' from user u inner join tmp_user tu on u.login=tu.login where input2_on_alert=1;
	insert into tmp_alert select  u.login, 'input2_off_alert' from user u inner join tmp_user tu on u.login=tu.login where input2_off_alert=1;

	insert into tmp_alert select  u.login, 'engine_on_alert' from user u inner join tmp_user tu on u.login=tu.login where engine_on_alert=1;
	insert into tmp_alert select  u.login, 'engine_off_alert' from user u inner join tmp_user tu on u.login=tu.login where engine_off_alert=1;    
    
	insert into tmp_alert select  u.login, 'power_on_alert' from user u inner join tmp_user tu on u.login=tu.login where power_on_alert=1;
	insert into tmp_alert select  u.login, 'power_off_alert' from user u inner join tmp_user tu on u.login=tu.login where power_off_alert=1;        
    
	insert into tmp_alert select  u.login, 'gps_on_alert' from user u inner join tmp_user tu on u.login=tu.login where power_on_alert=1;
	insert into tmp_alert select  u.login, 'gps_off_alert' from user u inner join tmp_user tu on u.login=tu.login where power_off_alert=1;            
                
	insert into tmp_alert select  u.login, 'station_in_alert' from user u inner join tmp_user tu on u.login=tu.login where station_in_alert=1;
	insert into tmp_alert select  u.login, 'station_out_alert' from user u inner join tmp_user tu on u.login=tu.login where station_out_alert=1;    
    
    insert into tmp_alert select  u.login, 'fixzone_out_alert' from user u inner join tmp_user tu on u.login=tu.login where fixzone_out_alert=1;    
    
	insert into tmp_alert select  u.login, 'restrictzone_in_alert' from user u inner join tmp_user tu on u.login=tu.login where restrictzone_in_alert=1;
	insert into tmp_alert select  u.login, 'restrictzone_out_alert' from user u inner join tmp_user tu on u.login=tu.login where restrictzone_out_alert=1;    
    
	insert into tmp_alert select  u.login, 'gps_on_alert' from user u inner join tmp_user tu on u.login=tu.login where gps_antenna_connect_alert=1;
	insert into tmp_alert select  u.login, 'gps_off_alert' from user u inner join tmp_user tu on u.login=tu.login where gps_antenna_disconnect_alert=1;    
    
    insert into tmp_alert select  u.login, 'hash_accelerate_alert' from user u inner join tmp_user tu on u.login=tu.login where hash_accelerate_alert=1;
	insert into tmp_alert select  u.login, 'drive4h_alert' from user u inner join tmp_user tu on u.login=tu.login where drive4h_alert=1;    
    
	select 	distinct u.login, t.plate_no, _msg_type as msg_type, _msg as msg
    from	tracker t 
			inner join user_tracker ut on t.imei=ut.tracker_imei
			inner join user u on ut.user_user_id=u.user_id
			inner join tmp_alert ta on u.login=ta.login
	where t.imei=_imei and ta.alert_type=_msg_type;
  
  end