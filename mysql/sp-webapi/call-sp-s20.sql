-- old api.
call sp_current_track_kw5('mcap', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_current_track('mcap', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('867747074412014','2026-08-01','2026-08-01','00:00','23:59',1,1000);
call sp_webapi_history_csv('867747074412014','2026-08-01','2026-08-01','00:00','23:59');

select 	* 
from 	gps_data_sum
where 	imei='867747074412014'
		and date(data_date)='2026-08-01';