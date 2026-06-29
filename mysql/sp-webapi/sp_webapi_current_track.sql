Drop procedure if exists sp_webapi_current_track;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_webapi_current_track`(
	in _login varchar(20),
    in _customer_group_id int,
	in _sortby varchar(80),
	in _direction varchar(4),
    in _keyword varchar(50),
    in _is_dltSynch varchar(1),
	in _status tinyint,
    in _offSet int,
    in _size int
)
BEGIN
            
	DECLARE done INT DEFAULT FALSE;
	DECLARE v_imei CHAR(20);
    declare v_plate_no varchar(50);
	Declare data_count int;
    DECLARE v_tracker_model VARCHAR(50);
    DECLARE v_event_codes VARCHAR(100);
    DECLARE v_sortby VARCHAR(80);
    DECLARE v_direction VARCHAR(4);
    

	DECLARE tCursor CURSOR FOR             
            Select  distinct t.imei, t.plate_no
			from 
					tracker t 
					inner join customer_tracker ct on ct.tracker_imei=t.imei
					inner join customer c on c.customer_id=ct.customer_customer_id 
					inner join customer_user cu on cu.customer_customer_id=c.customer_id
					inner join user_tracker ut on t.imei=ut.tracker_imei
					inner join user u on ut.user_user_id=u.user_id
					left join customer_group_tracker cgt on t.imei=cgt.imei
					left join customer_group cg on cgt.customer_group_id=cg.customer_group_id
                    
			where 	
					trim(u.login)=trim(_login)
					and (cgt.customer_group_id=_customer_group_id OR _customer_group_id=-1)
                    and (		t.imei 			like concat('%', coalesce(_keyword, t.imei),'%')
							or 	t.plate_no 		like concat('%', coalesce(_keyword, t.plate_no),'%')
							or 	t.tracker_model like concat(	 coalesce(_keyword, t.tracker_model),'%')
					)									
                    and t.dlt_synch = coalesce(nullif(_is_dltSynch,'0'), t.dlt_synch)
                    
            order by 
					case when _sortby='plate_no' and _direction='asc' then t.plate_no end asc,
                    case when _sortby='plate_no' and _direction='desc' then t.plate_no end desc,
                    case when _sortby='imei' and _direction='asc' then t.imei end asc,
                    case when _sortby='imei' and _direction='desc' then t.imei end desc
            limit _offset, _size        
            ;
            

	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;    

	DROP TEMPORARY TABLE IF EXISTS tmp_Current_Track;
	create temporary table tmp_Current_Track(
		id int auto_increment,
        gpsdata_id int, 
		sequen_no decimal(6,3),
		imei varchar(20),
        sim_no varchar(13),
		model varchar(15),
		plate_no nvarchar(50),		
		data_date datetime,
        received_date datetime,
		event_code varchar(3),
        engine_volt decimal(5,2),
        ext_power decimal(5,2),
		power_status varchar(3),
		gps_status varchar(1),
		lat decimal(12,8),
		lng decimal(12,8),
		heading decimal,
		state varchar(1),
		speed int,
		running bool,
		speed_limited int,
        fuel_left float,
        fuel_full_at smallint,
        temperature varchar(12),
        temp_a_min decimal(4,1),
        temp_a_max decimal(4,1),
        temp_b_min decimal(4,1),
        temp_b_max decimal(4,1),        
		input1 varchar(1),
		input2 varchar(1),
        output_engine_cut varchar(1),
		driver_name varchar(100),
		driver_phone varchar(30),
		fuel_price decimal(5,2),
		fuel_kmpl decimal(5,2),
		icon_path varchar(255),
        address varchar(255),
        track3 varchar(90),
        track1 varchar(50),
                
        ic_status tinyint,		-- 0=null, 1:off-line, 2=gps-v, 3=park, 4=acc-on, 5=start, 6=run
        dlt_synch tinyint,
        
-- 		msg_type varchar(30),
--         ev_time varchar(30),
		num_sats tinyint,
        primary key(id)
        
	)engine myisam;
    
    SET v_event_codes = CASE
        WHEN v_tracker_model IN ('T1', 'T333') THEN '35,3,11'
        WHEN v_tracker_model LIKE 'Totem%' THEN 'AA,21,22,02,03'
        WHEN v_tracker_model = 'Ruptela' THEN '7,8'
        WHEN v_tracker_model = 'Concox' THEN '12,16'
        WHEN v_tracker_model = 'FiFoTrack' THEN 'A01,4,5'
        WHEN v_tracker_model = 'iStartek' THEN '0'
        ELSE NULL
    END;

	OPEN tCursor;

		read_loop: LOOP

		FETCH tCursor INTO v_imei, v_plate_no;
		IF done THEN
		  LEAVE read_loop;
		END IF;

			insert into tmp_Current_Track(gpsdata_id, sequen_no, imei, sim_no, model, plate_no, data_date, received_date
										, event_code, engine_volt, ext_power, power_status, gps_status
                                        , lat, lng, heading, state, speed, running, speed_limited
										, fuel_left, fuel_full_at, temperature, temp_a_max, temp_a_min, temp_b_max, temp_b_min, input1, input2, output_engine_cut
                                        , driver_name, driver_phone, fuel_price, fuel_kmpl, icon_path, address, track3, track1
                                        , ic_status, dlt_synch, num_sats)
				select g.gpsdata_id, t.sequen_no, t.imei, t.sim_no, t.tracker_model, t.plate_no, DATE_ADD(g.data_date, INTERVAL 7 HOUR), received_date
						,g.event_code, t.engine_volt, g.ext_power, ifnull(g.ext_power_status,1) as ext_power_status, g.gps_status                        
						, st_x(g.g_point), st_y(g.g_point), g.heading, fn_acc_state(t.tracker_model, t.input_acc, g.state, g.speed), g.speed, 
						case when speed>0 then 1 else 0 end as running, t.speed_limited,						
						fn_fuel_percent(t.input_fuel, t.tracker_model, g.ad, t.fuel_min_vol, t.fuel_max_vol, t.input_fuel_reverse) as fuel_left
                        , t.fuel_full_at
						, fn_temperature(t.tracker_model, g.temp, t.input_temp)                        
						, tts.a_max, tts.a_min, tts.b_max, tts.b_min
						, fn_input(t.tracker_model, g.state, 1, t.input_1_reverse) as in1
						, fn_input(t.tracker_model, g.state, 2, t.input_2_reverse) as in2															
                        , fn_engine_cut_status(t.tracker_model,g.state,g.output_state) AS output_engine_cut											
						,t.driver_name, t.driver_phone, fuel_price, fuel_kmpl, t.icon_path, g.address, g.track3, g.track1
						,fn_ic_status(g.data_date,g.received_date,g.gps_status,g.state,t.input_acc,t.engine_volt,g.ext_power,g.speed) AS ic_status
						, t.dlt_synch, g.num_sats
					from 	tracker t 	
							left join gps_data g on t.imei=g.box_imei 									
							left join tracker_temp_sensor tts on t.imei=tts.imei                            
					where 	t.imei=v_imei 
							AND (v_event_codes IS NULL OR FIND_IN_SET(g.event_code, v_event_codes) > 0)
					order by g.data_date desc limit 0,1;
		END LOOP;

	close tCursor;
    
    -- select * from tmp_Current_Track;


    SET v_sortby = CASE
        WHEN lower(substring_index(_sortby, '.', -1)) IN ('data_date', 'gps_time', 'time') THEN 'date_sort'
        WHEN lower(substring_index(_sortby, '.', -1)) IN ('speed', 'fuel_left', 'ic_status', 'plate_no') THEN lower(substring_index(_sortby, '.', -1))
        WHEN lower(substring_index(_sortby, '.', -1)) = 'fuel' THEN 'fuel_left'
        WHEN lower(substring_index(_sortby, '.', -1)) = 'status' THEN 'ic_status'
        ELSE 'plate_no'
    END;

    SET v_direction = CASE
        WHEN lower(_direction) = 'desc' THEN 'desc'
        ELSE 'asc'
    END;

	SET @SQLStatement = CONCAT('
		select gpsdata_id, ifnull(sequen_no,id) as sequen_no, data_date as date_sort,
				DATE_FORMAT(data_date, ''%d-%m-%y %H:%i:%s'') as data_date, received_date, event_code, engine_volt, ext_power, power_status, state,
				Timestampdiff(minute, received_date, now()) as diff_minute,
				imei, sim_no, model, plate_no, tmp.lat, tmp.lng, gps_status, running, heading, speed, speed_limited, fuel_left,
				temperature, temp_a_min, temp_a_max, temp_b_min, temp_b_max, 
				input1, input2, output_engine_cut,
				driver_name, driver_phone, fuel_full_at, fuel_price, fuel_kmpl, icon_path, gac.address, track3, track1, ic_status, dlt_synch
				,num_sats
			from tmp_Current_Track tmp left join gps_address_cache gac on tmp.imei=gac.box_imei
		where ic_status=', if(_status=-1,'ic_status',_status), 
		' ORDER BY ', v_sortby, ' ' , v_direction
	);

	PREPARE stmt FROM @SQLStatement;
	EXECUTE stmt;
	
	drop temporary table tmp_Current_Track;

END
