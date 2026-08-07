Drop procedure if exists sp_rpt_speed;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_rpt_speed`(
    in _imei varchar(20),
    in _date1 varchar(20),
    in _date2 varchar(20),
    in _speed smallint,
    in _offset int,
    in _size int
)
    proc: begin

	DECLARE done INT DEFAULT FALSE;

    declare p_id int;
	declare p_imei, o_imei varchar(20);
	declare p_plate_no, o_plate_no varchar(50);
	declare p_date_time, o_date_time datetime;
	declare p_state, o_state varchar(1);
	declare p_speed, o_speed, _speed_limitted smallint;
	declare p_status, o_status varchar(1);
    declare p_point, o_point point;
    declare _tracker_model varchar(20);

    declare countN int;
	declare _plate_no varchar(50);
    declare _input_acc tinyint;
	declare x1, y1 decimal(12,8);
    declare startdt, o_dt1, p_dt1 datetime;
    declare o_point1 point;
    declare v_address varchar(255);
    declare _reportTbl varchar(50);
    declare _total_rows int default 0;

	DECLARE tCursor CURSOR FOR
        select * from tmpcursor
        order by data_date, gpsdata_id
        limit _offset, _size;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

	DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
GET DIAGNOSTICS CONDITION 1 @sqlstate = RETURNED_SQLSTATE,
    @errno = MYSQL_ERRNO, @text = MESSAGE_TEXT;
SET @full_error = CONCAT("ERROR ", @errno, " (", @sqlstate, "): ", @text);
SELECT @full_error;
END;

select	trim(t.plate_no), ifnull(t.input_acc,1), t.report_table, ifnull(t.speed_limited,0), t.tracker_model
into _plate_no, _input_acc, _reportTbl, _speed_limitted, _tracker_model
from 	tracker t left join gps_data g on t.imei=g.box_imei
where 	t.imei=_imei
	limit 0,1;

set _offset = greatest(ifnull(_offset, 0), 0);
set _size = greatest(ifnull(_size, 20), 1);

set @dropSQL = 'drop temporary table if exists tmpcursor';
prepare stmt from @dropSQL;
execute stmt;

Set @query = Concat('create temporary table tmpcursor (
							select g.gpsdata_id, g.box_imei, \'' , _plate_no, '\'
									,date_format(DATE_ADD(g.data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i:%s\') as data_date
									,substring(g.state, ', _input_acc, ', 1) as state, g.speed, g.g_point
							from	',_reportTbl,' g
							where
									g.box_imei = \'', _imei,'\'

                                    and (date_format(DATE_ADD(data_date, INTERVAL 7 HOUR), \'%Y-%m-%d %H:%i\') >= concat(\'', _date1, '\')
											and  date_format(DATE_ADD(data_date, INTERVAL 7 HOUR),\'%Y-%m-%d %H:%i\')  <= concat(\'', _date2, '\')
										)
                                    and g.speed >= ', _speed, '


									and case when \'',_tracker_model,'\' = \'t1\' 			then 	event_code in (\'35\',\'3\' ,\'11\') 				else true end
									and case when \'',_tracker_model,'\' = \'totemtech\' 	then	event_code in (\'AA\',\'21\',\'22\',\'02\',\'03\') 	else true end
									and case when \'',_tracker_model,'\' = \'totem-09\' 	then 	event_code in (\'AA\',\'21\',\'22\',\'02\',\'03\') 	else true end
									and case when \'',_tracker_model,'\' = \'ruptela\' 		then	event_code in (\'7\') 								else true end
									and case when \'',_tracker_model,'\' = \'concox\' 		then	event_code in (\'12\',\'16\') 						else true end
									and case when \'',_tracker_model,'\' = \'jc400\' 		then	event_code in (\'22\',\'95\') 						else true end
									and case when \'',_tracker_model,'\' = \'FiFoTrack\' 	then	event_code in (\'A01\') 							else true end
                                    and case when \'',_tracker_model,'\' = \'iStartek\' 	then	event_code in (\'0\',\'3\',\'4\') 					else true end

                                    and g.speed <= 160
                                    and g.gps_status <> \'V\'

							order by g.data_date, g.gpsdata_id
							)
						');


prepare stmt from @query;
execute stmt;
select count(*) into _total_rows from tmpcursor;

DROP TEMPORARY TABLE IF EXISTS tmp;
	create temporary table tmp(
		gpsdata_id int,
		imei varchar(20),
		plate_no varchar(50),
		data_date datetime,
		state varchar(1),
		speed mediumint,
        isSpeedOver bit,
		lat decimal(12,8),
		lng decimal(12,8),
        address varchar(255)
	)engine myisam;


OPEN tCursor;
read_loop: LOOP
	FETCH tCursor INTO p_id, p_imei, p_plate_no, p_date_time, p_state, p_speed, p_point;

	IF done THEN
      LEAVE read_loop;
END IF;

    set x1 = st_x(p_point);
    set y1 = st_y(p_point);

	set v_address = fn_geo_tambon_shp(x1, y1, 1);

insert into tmp(gpsdata_id, imei, plate_no, data_date, state, speed, isSpeedOver, lat, lng, address)
values(p_id, p_imei, p_plate_no, p_date_time, p_state, p_speed,
       case when _speed_limitted is not null then if(p_speed>_speed_limitted,1,0) else 0 end, x1, y1, v_address);


END LOOP;

/* result set 1: summary */
select _total_rows as total_rows,
       coalesce(round(avg(speed), 2), 0) as average_speed,
       coalesce(max(speed), 0) as max_speed,
       coalesce(sum(case when speed > _speed_limitted then 1 else 0 end), 0) as speed_over_rows,
       _speed_limitted as speed_limited
from tmpcursor;

/* result set 2: pagination */
select floor(_offset / _size) + 1 as current_page,
       _size as per_page,
       _offset as offset,
       _total_rows as total_rows,
       ceil(_total_rows / _size) as total_pages;

/* result set 3: rows */
select gpsdata_id, imei, plate_no, data_date, state, speed, isSpeedOver, lat, lng, address
from tmp
order by data_date, gpsdata_id;

end$$

DELIMITER ;
