DELIMITER $$
DROP FUNCTION IF EXISTS fn_temperature $$

CREATE FUNCTION `fn_temperature`(
	p_model varchar(15),
	p_string varchar(24),
	p_temp_input tinyint
    
) RETURNS decimal(5,1)
    DETERMINISTIC
BEGIN

	declare ad_hex varchar(4);
	declare ad_dec int;
	declare resistance decimal(8,4);
	declare temp int;
    declare temp_return decimal(5,1);

-- 	if p_temp_input is null 
-- 		-- OR p_temp_input = -1 then
--         then
-- 		return null;
-- 	end if;
    
    if p_model = 'T1' then
		set ad_hex = SPLIT_STRING('|', p_string, p_temp_input);
		set ad_dec = conv(ad_hex, 16, 10);
		
		set resistance = (4700*ad_dec)/(1024-ad_dec)/1000;
		set temp = (select t from temp_resistance where (resistance >= r_min) and (resistance <= r_max) limit 0,1);
		if temp is null then
			set temp = (select t from temp_resistance order by abs(r_center-resistance) asc limit 0,1);
		end if;        
        set temp_return =  cast(temp as decimal(5,1));
        
	elseif p_model = 'Totemtech' then
		set temp_return = cast(p_string as decimal(5,1));	
    elseif p_model = 'Totem-107-3G' then
		set temp_return = cast(p_string as decimal(5,1));	
	elseif p_model = 'Totem-107-4G' then
		set temp_return = cast(p_string as decimal(5,1));	
	elseif p_model = 'Totem-109-3G' then
		set temp_return = g.temp;
	elseif p_model = 'Ruptela' then
		set temp_return = cast(p_string as decimal(5,1));
	elseif p_model = 'Teltonika' then
		set temp_return = g.temp;
    end if;

	
	RETURN temp_return;

END