Drop procedure if exists `sp_station_in2`;

Delimiter $$

CREATE PROCEDURE `sp_station_in2`(
	in _imei varchar(20),
    in _data_date datetime,
    in _x decimal(12,8),
    in _y decimal(12,8),
    in _gpsdata_id int
)
proc: begin

declare p_station_id int;
declare p_customer_id int;
declare p_datareport varchar(14);
declare p_tblstation_data varchar(20);

DECLARE done INT DEFAULT FALSE;
DECLARE tCursor CURSOR FOR 
							select 	s.station_id, c.customer_id, t.report_table
							from 	station s 	
									inner join customer c on s.customer_customer_id=c.customer_id
									inner join customer_tracker ct on c.customer_id=ct.customer_customer_id
									inner join tracker t on ct.tracker_imei=t.imei
							where 	t.imei=_imei and (s.station_type='circle' or s.station_type is null)
									AND fn_distance(_x,_y, st_x(s.station_point), st_y(s.station_point))*1000 <= s.radius
                                    
							union all
                            
							select 	s.station_id, c.customer_id, t.report_table
							from 	station s 	
									inner join customer c on s.customer_customer_id=c.customer_id
									inner join customer_tracker ct on c.customer_id=ct.customer_customer_id
									inner join tracker t on ct.tracker_imei=t.imei
							where 	t.imei=_imei and s.station_type='polygon'
									-- AND st_within(point(_x,_y), s.station_polygon)=1;
                                    and mbrcontains(s.station_polygon, point(_x,_y))=1;


DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;                                
set p_tblstation_data='';
OPEN tCursor;

	read_loop: LOOP

	FETCH tCursor INTO p_station_id, p_customer_id, p_datareport;
		IF done THEN
		  LEAVE read_loop;
		END IF;

        set p_tblstation_data = replace(p_datareport,'data_report','station_data');

        set @query = concat('
								Insert into ',  p_tblstation_data, '(data_date, imei, g_point, station_station_id, gpsdata_id) 
															values (
															\''  , _data_date, '\'
															,\'' , _imei          , '\'
															,     st_GeometryFromText(\'point(' , _x, ' ' , _y ,')\')
															,'  , p_station_id, '
                                                            ,' ,_gpsdata_id, ')
							');         

-- 		select @query;
        prepare stmt from @query;
		execute stmt;
    
	END LOOP;

close tCursor;


end