-- old api.
call sp_current_track_kw5('sirima', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_current_track('sirima', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('862129080161940','2026-08-01','2026-08-01','00:00','23:59',1,1000);
call sp_webapi_history_csv('862129080161940','2026-08-01','2026-08-01','00:00','23:59');

select 	* 
from 	gps_sum_data
where 	imei='862129080161940'
		and date(data_date)='2026-08-10';