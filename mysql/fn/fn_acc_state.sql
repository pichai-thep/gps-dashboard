drop function if exists fn_acc_state;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE FUNCTION `fn_acc_state`(
	_tracker_model varchar(15),
    _input_acc tinyint,
    _state varchar(32),
    _speed int
) RETURNS char(1)
BEGIN

	declare _ret_acc_status char(1) default '0';
    
    if _state is null then 
		return '0';	
    end if;    
    
    if length(_state)>0 then
		
        CASE _tracker_model
			when 'JM-LL301' then        
				set _ret_acc_status = if(_speed>=5, 1, 0);
			ELSE				
                if _input_acc is null then
					set _ret_acc_status = substring(_state,1, 1);		
				else
					set _ret_acc_status = substring(_state, _input_acc, 1);	
				end if;
		END CASE;
    
	else
		set _ret_acc_status = substring(_state,1, 1);		
	end if;
    

RETURN _ret_acc_status;
END