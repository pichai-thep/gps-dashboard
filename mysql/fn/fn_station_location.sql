drop function if exists `fn_station_location`;

Delimiter $$

create function `fn_station_location`(
	p_cust_id int,
	p_lon decimal(11,8),
    p_lat decimal(11,8)	
) returns varchar(100)
	deterministic
begin

	declare p_station_name varchar(100);
    
    select 	s.station_name into p_station_name
	from 	stations s 	
	where 	customer_customer_id=p_cust_id
			AND (s.station_type='circle' or s.station_type is null)
			AND fn_distance(p_lat,p_lon, st_x(s.station_point), st_y(s.station_point))*1000 <= s.radius
	limit 0,1;
			
	if p_station_name is not null then
		return p_station_name;
	end if;
	
	select 	s.station_name into p_station_name
	from 	stations s 	
    where 	customer_customer_id=p_cust_id
			AND s.station_type='polygon'
			and mbrcontains(s.station_polygon, point(p_lat,p_lon))=1
	limit 0,1;

	if p_station_name is not null then
		return p_station_name;    
	else
		return concat(p_lat, ',', p_lon);
    end if;

end;



