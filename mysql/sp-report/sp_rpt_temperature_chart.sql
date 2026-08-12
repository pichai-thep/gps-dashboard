drop procedure if exists `sp_rpt_temperature_chart`;

Delimiter $$

CREATE  PROCEDURE `sp_rpt_temperature_chart`(
	in _imei varchar(20),
	in _date1 varchar(10),
    in _date2 varchar(10),
    in _time1 varchar(5),
    in _time2 varchar(5),
    in _no int
)
proc: begin

declare _rptTable varchar(20);
declare im_time1, im_time2 datetime;
declare p_ext_power, _engine_volt decimal(5,2);
declare _input_temp tinyint;
declare _tracker_model varchar(20);

set _rptTable = 'data_report';

select tracker_model, input_temp, report_table into _tracker_model, _input_temp, _rptTable from tracker where imei=_imei;

DROP TEMPORARY TABLE IF EXISTS tmp_data;
CREATE TEMPORARY TABLE tmp_data (
	`plate_no` varchar(50),
	`data_date` datetime,
    `state` varchar(1),
    `speed` int,
    `engine_volt` decimal(5,2),
    `ext_power` decimal(5,2),
    `temperature` decimal(4,1)
)ENGINE=MyISAM;

set @query = concat('
		
        insert 	into tmp_data(plate_no, data_date, state, speed, engine_volt, ext_power, temperature)
        select 	t.plate_no, DATE_ADD(g.data_date, INTERVAL 7 HOUR)
                ,case when length(g.state)>1 then 
					substr(g.state, input_acc,1)  
				else ifnull(g.state,\'0\') end
                , g.speed
                , ', ifnull(_engine_volt,'null'), ', g.ext_power
				,case when ',ifnull(_input_temp,-1),' > \'0\' then 
				case \'', _tracker_model, '\' 
					when \'totem-107-2g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'totem-107-3g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'Totem-107-4g\' then
						fn_temperature_totem(g.temp, ', ifnull(_input_temp,-1) ,')
                    when \'Totem-109-3g\' then						
                        fn_temperature_totem09_select_sensor(g.temp, ', ifnull(_input_temp,-1) ,',', _no,')
					when \'Ruptela\' then
						fn_temperature_ruptela(g.temp, ', ifnull(_input_temp,-1) ,')
					when \'Teltonika\' then
						fn_temperature_teltonika(g.temp, ', ifnull(_input_temp,-1) ,')
				end
			else null end as temperature
        from ', _rptTable, ' g 
				left join tracker t on g.box_imei=t.imei
		where	
				box_imei = \'', ifnull(_imei,''),'\'
				
                and (date_format(DATE_ADD(data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i\') >= concat(\'', _date1, ' \', \'', _time1, '\')
												and  date_format(DATE_ADD(data_date, INTERVAL 7 HOUR),\'%Y-%m-%d %H:%i\')  <= concat(\'', _date2, ' \',\'',  _time2, '\')
											)										
                
				and g.g_point is not null 
				and case when t.tracker_model = \'totem-107-2g\' 	then	event_code in (\'AA\',\'21\',\'22\') 	else true end
				and case when t.tracker_model = \'totem-107-3g\' 	then	event_code in (\'AA\',\'21\',\'22\') 	else true end
				and case when t.tracker_model = \'totem-107-4g\' 	then	event_code in (\'AA\',\'21\',\'22\') 	else true end
				and case when t.tracker_model = \'totem-109-3G\' 	then 	event_code in (\'AA\',\'21\',\'22\') 	else true end
				and case when t.tracker_model = \'ruptela\' 	then	event_code in (\'7\') 					else true end                   
                and case when t.tracker_model = \'Teltonika\' 	then	event_code in (\'0\') 					else true end

');

-- select @query;
-- leave proc;

prepare stmt from @query;
execute stmt;
-- leave proc;

-- select * from tmp_data;
-- leave proc;

DROP TEMPORARY TABLE IF EXISTS mm;
CREATE TEMPORARY TABLE mm (
  `mm_time` datetime NOT NULL,
  primary key(`mm_time`)
)ENGINE=MyISAM;
        
    set im_time1 = concat(_date1, ' ', _time1);
    set im_time2 = concat(_date2, ' ', _time2);
    
    while (im_time1 <= im_time2) do
		insert into mm(mm_time) values (im_time1);
        set im_time1 = date_add(im_time1, interval 1 minute);
    end while; 
    
    select 	tmp_data.plate_no, 
			mm.mm_time, tmp_data.data_date,
            tmp_data.state, 
            tmp_data.speed, 
            tmp_data.engine_volt, tmp_data.ext_power,
			tmp_data.temperature as temperature
    from 	mm 	
			left join tmp_data on date_format(mm.mm_time,'%Y-%m-%d %H:%i') = date_format(tmp_data.data_date,'%Y-%m-%d %H:%i')	
    order by mm.mm_time;

-- select @query;
-- leave proc;
  
-- prepare stmt from @query;
-- execute stmt;
        
end