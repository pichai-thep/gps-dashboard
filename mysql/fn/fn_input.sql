drop function if exists `fn_input`;

Delimiter $$

CREATE FUNCTION `fn_input`(
    p_model varchar(15),
    p_str_state varchar(32),
    p_input_no tinyint,
    p_reverse tinyint
    
) RETURNS varchar(1) CHARSET utf8
    DETERMINISTIC
BEGIN
	declare ret_state varchar(1);
    set ret_state = null;
    
    
    if p_str_state is null then 
		return null;	
    end if;
    
    if length(p_str_state) = 0 then 
		return null;	
    end if;
    
    if p_model = 'T1' then
    
		set ret_state = case p_input_no
							when 1 then substring(p_str_state, 8, 1)
							when 2 then substring(p_str_state, 7, 1)
							when 3 then substring(p_str_state, 6, 1)
							when 4 then substring(p_str_state, 5, 1)
                            when 5 then substring(p_str_state, 4, 1)
                            when 6 then substring(p_str_state, 3, 1)
                            when 7 then substring(p_str_state, 2, 1)
                            when 8 then substring(p_str_state, 1, 1)
                            else null
						end;

	elseif p_model = 'Topflytech' then
		
        set ret_state = case p_input_no
							when 1 then substring(p_str_state, 4, 1)
							when 2 then substring(p_str_state, 5, 1)
                            else null
						end;		
                        
	elseif p_model = 'Totemtech' OR p_model = 'Totem-107-2G' OR p_model = 'Totem-107-3G' OR p_model = 'Totem-107-4G' then
    
		set ret_state = case p_input_no
							when 1 then substring(p_str_state, 12, 1)	-- IN2
							when 2 then substring(p_str_state, 13, 1)	-- IN4
                            else null
						end;
                        
	elseif p_model = 'Totem-09'  OR p_model = 'Totem-109-3G' OR p_model = 'Totem-109-4G' then
    
		set ret_state = case p_input_no
                            when 1 then substring(p_str_state, 2, 1)
							when 2 then substring(p_str_state, 13, 1)
                            else null
						end;
                        
	end if;
    
    if p_reverse=1 then
		set ret_state = if(ret_state=1,0,1);    
	end if;
    
    return ret_state;

END