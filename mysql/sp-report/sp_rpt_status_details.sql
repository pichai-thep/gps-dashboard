Drop procedure if exists sp_rpt_status_details;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_rpt_status_details`(
	in _imei varchar(20),
    in _cust_id int,
	in _date1 varchar(20),
	in _date2 varchar(20),
    in _status varchar(5),
    in _duration int    

)
proc: begin

	DECLARE done INT DEFAULT FALSE;
    declare min_id, max_id int;
    declare p_date, o_date varchar(10);
	DECLARE p_imei, o_imei varchar(20);
	DECLARE p_plate_no, o_plate_no varchar(50);
	DECLARE p_date_time, o_date_time, e_date_time datetime;
	DECLARE p_gps_status varchar(1);	
	DECLARE p_state, o_state varchar(1);
	DECLARE p_status, o_status varchar(6);
	DECLARE p_speed, o_speed, v_max_speed int;
	DECLARE p_point, o_point point;
	DECLARE countN int;
	declare _rptTable varchar(20);
    declare p_distance decimal(8,3);
	declare x1, y1, x2, y2 decimal(12,8);
    declare p_ext_power, _engine_volt decimal(5,2);

	DECLARE tCursor CURSOR FOR Select * from tmpcursor;
	
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
	DECLARE EXIT HANDLER FOR SQLEXCEPTION    
	BEGIN
	 GET DIAGNOSTICS CONDITION 1
		@p2 = MESSAGE_TEXT;
		SELECT @p2;
	END;
    
    -- SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED ;
    
    set _rptTable = 'data_report';
    
    select report_table, engine_volt into _rptTable, _engine_volt from tracker where imei=_imei;

	set @dropSQL = 'drop temporary table if exists tmpcursor';
	prepare stmt from @dropSQL;
	execute stmt;
    
	Set @query = Concat('create temporary table tmpcursor (
								select 	t.imei, t.plate_no, date_format(DATE_ADD(data_date, INTERVAL 7 HOUR), \'%Y-%m-%d\'),
								date_format(DATE_ADD(data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i:%s\') as data_date, g.gps_status,
                                case when length(state)>1 then substr(state, input_acc,1)  else ifnull(state,\'0\') end as state,
                                speed, g_point, ext_power
						from
								tracker t                 
								inner join (select * from ', _rptTable, ' 
												where
															(	date_format(DATE_ADD(data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i\') >= concat(\'', _date1,  '\')
																And 
																date_format(DATE_ADD(data_date, INTERVAL 7 HOUR),\'%Y-%m-%d %H:%i\') <= concat(\'', _date2, '\')
															)
								
												) g on g.box_imei=t.imei
                        where	t.imei = \'',_imei, '\'
								
								
                                and case when t.tracker_model = \'t1\' 			then 	g.event_code in (\'35\',\'3\' ,\'11\') 		else true end
								and case when t.tracker_model = \'totemtech\' 	then	g.event_code in (\'AA\',\'21\',\'22\') 		else true end
								and case when t.tracker_model = \'totem-09\' 	then 	g.event_code in (\'AA\',\'21\',\'22\') 		else true end
								and case when t.tracker_model = \'ruptela\' 	then	g.event_code in (\'7\') 					else true end
								and case when t.tracker_model = \'concox\' 		then	g.event_code in (\'12\',\'16\') 			else true end
								and case when t.tracker_model = \'jc400\' 		then	g.event_code in (\'22\',\'95\') 			else true end
								and case when t.tracker_model = \'FiFoTrack\' 	then	g.event_code in (\'A01\') 					else true end
                                
                                and g.speed <= 160
								and st_x(g_point) <> 0 and st_y(g_point) <> 0
								and g.g_point is not null 
								and g.gps_status <> \'V\'

					order by data_date)');
	
    

-- 	select @query;
--     leave proc;
    
	prepare stmt from @query;
	execute stmt;
	
    -- select * from tmpcursor;



	DROP TEMPORARY TABLE IF EXISTS tmp_state1;
	create temporary table tmp_state1(
		id int not null auto_increment,
		imei varchar(20),	
		plate_no varchar(50),
		data_date datetime,
        speed int,
		state_status varchar(6),
		g_point point,
	primary key(`id`)
    )ENGINE=MyISAM;

	DROP TEMPORARY TABLE IF EXISTS tmp_state2;
	create temporary table tmp_state2(
		idx int auto_increment,
		id int,
		imei varchar(20),	
		plate_no varchar(50),
		start_date_time datetime,
		end_date_time datetime,
        start_point point,
        end_point point,
        start_station varchar(100),
        end_station varchar(100),
        end_address varchar(200),
        max_speed int,
        distance decimal(8,3),
		state_status varchar(15),
		state_minute int,
        attendance_by varchar(20),
        primary key(`idx`)
	)ENGINE=MyISAM;

	set countN = 0;
    set p_distance = 0;
	OPEN tCursor;

	read_loop: LOOP

	FETCH tCursor INTO p_imei, p_plate_no, p_date, p_date_time, p_gps_status, p_state, p_speed, p_point, p_ext_power;
		

	IF done THEN
      LEAVE read_loop;
    END IF;

	-- if p_plate_no = 'Totem-v3.08n' then leave read_loop; end if;

	set countN = countN+1;
	
    if (countN=1) then
		
		set x1 = st_x(p_point);
		set y1 = st_y(p_point);
	else
    	set x1 = st_x(o_point);
		set y1 = st_y(o_point);
	end if;
    
	set x2 = st_x(p_point);
	set y2 = st_y(p_point);
    
    
	if (o_status = 'run') then
		set p_distance = p_distance + ifnull((select fn_distance(x1, y1, x2, y2)),0);
	end if;
    
	if (p_gps_status='A') then
		if (p_state='1') then       
			if (p_speed > 0) then
				set p_status = 'run';
			else
				set p_status = 'idle';
            end if;                
		else 
			set p_status = 'park';
		end if;
	else
		set p_status = 'gpsv';
	end if;

	insert into tmp_state1(imei, plate_no, data_date, speed, state_status, g_point) 
			values(p_imei, p_plate_no, p_date_time, p_speed, p_status, p_point);	



	if  (p_date <> o_date OR p_imei <> o_imei OR p_status <> o_status) and countN > 1 then
			
            select min(id), max(id) into min_id, max_id from tmp_state1;
            if (o_status='run') then
				set p_distance = p_distance + ifnull((select fn_distance(x1, y1, x2, y2)),0);
            end if;
            
            insert into tmp_state2(id, imei, plate_no, start_date_time, start_point, state_status, start_station) 
							select id, imei, plate_no, data_date, g_point, state_status, fn_station_in(_cust_id, st_x(g_point), st_y(g_point))
							from tmp_state1 
                            where id=min_id;
			
			-- set e_date_time = o_date_time;
            
            select max(speed) into v_max_speed from tmp_state1 where imei=o_imei and state_status=o_status;
			update tmp_state2 set 
				end_date_time = o_date_time,
                end_point = o_point,
                end_station = fn_station_in(_cust_id, st_x(o_point), st_y(o_point)),
                end_address = fn_geo_tambon_shp(st_x(o_point), st_y(o_point),1),
                max_speed = v_max_speed,
                distance = p_distance,
				state_minute = timestampdiff(minute, start_date_time, o_date_time)
			where id=min_id;
			delete from tmp_state1 where imei=o_imei and state_status=o_status;
		
        set p_distance = 0;
			
	end if;

	set o_imei = p_imei;
    set o_date = p_date;
    set o_date_time = p_date_time;
	set o_plate_no = p_plate_no;
	set o_status = p_status;
    set o_point = p_point;
	
    END LOOP;

	close tCursor;
    
--     select * from tmp_state1;
--     select * from tmp_state2;
--     leave proc;
    
    if (o_status = 'run') then
		set p_distance = p_distance + ifnull((select fn_distance(x1, y1, x2, y2)),0);
	end if;
    
	select min(id), max(id) into min_id, max_id from tmp_state1;
            
	insert into tmp_state2(id, imei, plate_no, start_date_time, start_point, state_status
							, start_station
                            ) 
					select id, imei, plate_no, data_date, g_point, state_status
                    , fn_station_in(_cust_id, st_x(g_point), st_y(g_point))
					from tmp_state1 
					where id=min_id;
	
	select max(speed) into v_max_speed from tmp_state1 where imei=o_imei and state_status=o_status;
	update tmp_state2 set 
		end_date_time = o_date_time,
		end_point = o_point,
        end_station = fn_station_in(_cust_id, st_x(o_point), st_y(o_point)),
		max_speed = v_max_speed,
		distance = p_distance,
		state_minute = timestampdiff(minute, start_date_time, o_date_time)
	where id=min_id;
	delete from tmp_state1 where imei=o_imei and state_status=o_status; 
                                            
            
    
	select 	(@row_number:=@row_number + 1) AS num, idx, id, tmp2.imei, plate_no
			, state_status
			, date_format(start_date_time, '%d/%m/%Y %H:%i:%s') as start_date_time
			, date_format(end_date_time, '%d/%m/%Y %H:%i:%s') as end_date_time
			, format(st_x(start_point),8) as start_lat, format(st_y(start_point),8) as start_lng
			, format(st_x(end_point),8) as end_lat, format(st_y(end_point),8) as end_lng
            , start_station, end_station, end_address
			, max_speed, distance
			
            , timediff(end_date_time, start_date_time) as duration_time            
            , timestampdiff(minute, start_date_time, end_date_time) as duration_mm
            , timestampdiff(second, start_date_time, end_date_time) as duration_ss
            , state_minute
	from 	tmp_state2 tmp2          
    
	where 	
			state_status = coalesce(_status, state_status)
	order by start_date_time, end_date_time;
            
	deallocate prepare stmt;
	drop temporary table tmp_state1;
	drop temporary table tmp_state2;
        

end