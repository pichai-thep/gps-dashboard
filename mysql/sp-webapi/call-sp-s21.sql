-- old api.
call sp_current_track_kw5('admin_bmgroup', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_current_track('admin_bmgroup', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('866728061457767','2026-08-01','2026-08-01','00:00','23:59',1,1000);
call sp_webapi_history_csv('866728061457767','2026-08-01','2026-08-01','00:00','23:59');

select 	* 
from 	gps_sum_data
where 	imei='866728061457767'
		and date(data_date)='2026-08-10';