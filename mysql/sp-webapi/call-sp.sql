-- old api.
call sp_current_track_kw5('mcap', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_current_track('mcap', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('863835029900888','2026-05-01','2026-05-30','00:00','23:59',1,1000);
call sp_webapi_history_csv('864606041741959','2026-05-20','2026-05-30','00:00','23:59');

select 	* 
from 	gps_data_sum
where 	imei='864606041741959'
		and date(data_date)='2026-05-27';