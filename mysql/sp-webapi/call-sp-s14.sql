-- old api.
call sp_current_track_kw5('manas', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_current_track('manas', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('862129084608540','2026-08-11','2026-08-11','00:00','23:59',1,1000);
call sp_webapi_history_csv('862129084608540','2026-08-11','2026-08-11','00:00','23:59');

select 	* 
from 	gps_sum_data
where 	imei='862129084608540'
		and date(data_date)='2026-08-10';