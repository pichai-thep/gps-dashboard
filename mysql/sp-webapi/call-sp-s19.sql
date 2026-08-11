-- old api.
call sp_current_track_kw5('hathairat', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_current_track('hathairat', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('863235083193357','2026-08-01','2026-08-01','00:00','23:59',1,1000);
call sp_webapi_history_csv('863235083193357','2026-08-01','2026-08-01','00:00','23:59');

select 	* 
from 	gps_data_sum
where 	imei='863235083193357'
		and date(data_date)='2026-08-10';