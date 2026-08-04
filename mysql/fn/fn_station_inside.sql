drop function if exists `fn_station_inside`;

Delimiter $$

create function `fn_station_inside`(
	p_cust_id int,
	p_lon decimal(11,8),
    p_lat decimal(11,8)	
) returns tinyint
	deterministic
begin

	declare p_inside_count tinyint;
    set p_inside_count=0;
    
    select  count(s.station_id) into p_inside_count
	from 	stations s 	
	where 	customer_customer_id=p_cust_id
			AND (s.station_type='circle' or s.station_type is null)
			AND fn_distance(p_lat,p_lon, st_x(s.station_point), st_y(s.station_point))*1000 <= s.radius
	limit 0,1;
			
	if p_inside_count > 0 then
		return p_inside_count;
	end if;
	
	select  count(s.station_id) into p_inside_count
	from 	stations s 	
    where 	customer_customer_id=p_cust_id
			AND s.station_type='polygon'
			and mbrcontains(s.station_polygon, point(p_lat,p_lon))=1
	limit 0,1;

	return p_inside_count;

end;



