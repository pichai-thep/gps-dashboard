Drop procedure if exists sp_rpt_temperature;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_rpt_temperature`(
	in `_cust_id` int,
	in `_imei` varchar(20),
	in `_date1` varchar(20),
    in `_date2` varchar(20),
    in `_time1` varchar(20),
    in `_time2` varchar(20),
    in `_temp_status` varchar(6)
)
proc: begin



declare _input_temp tinyint;
declare _tracker_model varchar(20);
declare _rptTable varchar(20);
declare _temp_a_min, _temp_a_max, _temp_b_min, _temp_b_max decimal(4,1);
declare _input_1_reverse, _input_2_reverse tinyint;
declare p_imei varchar(20);
declare p_plate_no varchar(50);
declare p_data_date datetime;
declare p_state varchar(1);
declare p_vehicle_status varchar(5);
declare p_speed smallint;
declare p_in1, p_in2 varchar(1);
declare p_temp_a decimal(4,1);
declare p_temp_b decimal(4,1);
declare p_lat, p_lon decimal(11,8);
declare _temp_a_status, _temp_b_status varchar(10);
declare v_address varchar(255);


DECLARE done INT DEFAULT FALSE;
DECLARE tCursor CURSOR FOR Select * from tmpcursor;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

select	tracker_model, input_temp, report_table, tts.a_min, tts.a_max, tts.b_min, tts.b_max, t.input_1_reverse, t.input_2_reverse
into 	_tracker_model, _input_temp, _rptTable, _temp_a_min, _temp_a_max, _temp_b_min, _temp_b_max, _input_1_reverse, _input_2_reverse 
from tracker t left join tracker_temp_sensor tts on t.imei=tts.imei
where t.imei=_imei;


set @dropSQL = 'drop temporary table if exists tmpcursor';
prepare stmt from @dropSQL;
execute stmt;


Set @query = Concat('create temporary table tmpcursor (
    
	select 	
			t.imei, t.plate_no
			, date_format(DATE_ADD(g.data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i:%s\') as data_date
            ,case when length(g.state)>1 then 
					substr(g.state, input_acc,1)  
				else ifnull(g.state,\'0\') end as state
			, g.speed
			, case when substr(state, input_acc, 1)=\'1\' then
				if(g.speed>0, \'run\', \'start\')
			  else \'stop\' end as status
                        
			,fn_input(\'', _tracker_model, '\', g.state, 1, ', ifnull(_input_1_reverse,-1), ') as in1
			,fn_input(\'', _tracker_model, '\', g.state, 2, ', ifnull(_input_2_reverse,-1), ') as in2									                        
            ,case when ',ifnull(_input_temp,-1),' > \'0\' then 
				case \'', _tracker_model, '\' 
					when \'totem-107-2g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'totem-107-3g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'Totem-107-4g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
                    when \'Totem-109-3g\' then
						fn_temperature_totem09_select_sensor(g.temp, ', ifnull(_input_temp,-1) ,', 1)
					when \'Ruptela\' then
						fn_temperature_ruptela(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'Teltonika\' then
						fn_temperature_teltonika(g.temp, ', ifnull(_input_temp,-1) ,')
				end
			else null end as temperature1
            ,case when ',ifnull(_input_temp,-1),' > \'0\' then 
				case \'', _tracker_model, '\' 
					when \'totem-107-2g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'totem-107-3g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'totem-107-4g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
                    when \'totem-109-3g\' then
						fn_temperature_totem09_select_sensor(g.temp, ', ifnull(_input_temp,-1) ,', 2)
					when \'Ruptela\' then
						fn_temperature_ruptela(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'Teltonika\' then
						fn_temperature_teltonika(g.temp, ', ifnull(_input_temp,-1) ,')
				end
			else null end as temperature2		
            ,st_x(g.g_point), st_y(g.g_point)
            
    from ', _rptTable, ' g inner join tracker t on g.box_imei=t.imei
	where
            g.box_imei = \'', _imei, '\'
            and (date_format(DATE_ADD(data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i\') >= concat(\'', _date1, ' \', \'', _time1, '\')
												and  date_format(DATE_ADD(data_date, INTERVAL 7 HOUR),\'%Y-%m-%d %H:%i\')  <= concat(\'', _date2, ' \',\'',  _time2, '\')
											)										
			and g.g_point is not null 
			and case when t.tracker_model = \'totem-107-2g\' 	then	event_code in (\'AA\',\'21\',\'22\') 	else true end
            and case when t.tracker_model = \'totem-107-3g\' 	then	event_code in (\'AA\',\'21\',\'22\') 	else true end
            and case when t.tracker_model = \'totem-107-4g\' 	then	event_code in (\'AA\',\'21\',\'22\') 	else true end
			and case when t.tracker_model = \'totem-109-3G\' 	then 	event_code in (\'AA\',\'21\',\'22\') 	else true end
			and case when t.tracker_model = \'ruptela\' 		then	event_code in (\'7\') 					else true end               
            and case when t.tracker_model = \'Teltonika\' 		then	event_code in (\'0\') 				else true end
    order by g.data_date
    )
');
	        
-- select @query;
-- leave proc;

prepare stmt from @query;
execute stmt;

DROP TEMPORARY TABLE IF EXISTS tmp_temp;
create temporary table tmp_temp(
	imei varchar(20),
    plate_no varchar(50),
	data_date datetime,
	vehicle_status varchar(5),
	speed mediumint,
	temp_a decimal(4,1),
	temp_b decimal(4,1),
	temp_a_status varchar(6),	-- green: normal, yellow: abnormal, red: abnormal in working
	temp_b_status varchar(6),	-- green: normal, yellow: abnormal, red: abnormal in working        
    input2 varchar(1),
    lat decimal(11,8),
    lon decimal(11,8),
    address varchar(255)
)engine myisam;
        
OPEN tCursor;
	read_loop: LOOP
	FETCH tCursor INTO 	p_imei, p_plate_no, p_data_date, p_state, p_speed, p_vehicle_status, p_in1, p_in2, p_temp_a, p_temp_b, p_lat, p_lon;		    	
	IF done THEN
      LEAVE read_loop;
    END IF;    
    
    if (p_temp_a < _temp_a_min) OR (p_temp_a > _temp_a_max) then
		if (p_in2='1') then
			set _temp_a_status = 'red';
        else
			set _temp_a_status = 'yellow';		
		end if;
	else
		set _temp_a_status = 'green';
    end if;
    
    if (p_temp_b < _temp_b_min) OR (p_temp_b > _temp_b_max) then
		if (p_in2='1') then
			set _temp_b_status = 'red';
        else
			set _temp_b_status = 'yellow';
		end if;
	else
		set _temp_b_status = 'green';
    end if;
    
    set v_address = (select fn_geo_tambon_shp(p_lat, p_lon, 1));
    
    insert into tmp_temp(imei, plate_no, data_date, vehicle_status, speed, temp_a, temp_b, temp_a_status, temp_b_status, input2, lat, lon, address)
		values(p_imei, p_plate_no, p_data_date, p_vehicle_status, p_speed, p_temp_a, p_temp_b, _temp_a_status, _temp_b_status, p_in2, p_lat, p_lon, v_address);
    
END LOOP;    

/* Result set 1: sensor configuration and averages for the selected period */
select
	_temp_a_min as sensor_a_min,
    _temp_a_max as sensor_a_max,
    round(avg(temp_a), 1) as sensor_a_average,
    _temp_b_min as sensor_b_min,
    _temp_b_max as sensor_b_max,
    round(avg(temp_b), 1) as sensor_b_average
from tmp_temp;

/* Result set 2: report rows */
select 	*		
from 	tmp_temp
where	temp_a_status like coalesce(nullif(_temp_status,'all'), temp_a_status) OR temp_b_status like coalesce(nullif(_temp_status,'all'), temp_b_status)
;

drop temporary table tmp_temp;

end$$

DELIMITER ;
