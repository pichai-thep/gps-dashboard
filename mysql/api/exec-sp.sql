-- 	in _login varchar(20), in _customer_group_id int, in _sortby varchar(80), in _direction varchar(4),
--     in _keyword varchar(50), in _is_dltSynch varchar(1), in _status tinyint, in _offSet int, in _size int
    
call sp_webapi_current_track('all-tu', -1, 'plate_no', 'asc', null, null, -1, 0, 100);

call sp_api_current_track_passenger('all-tu', -1, 'imei', 'asc', '', 0, -1, 0, 100);

call sp_webapi_history('864606041741959','2026-05-01','2026-05-30','00:00','23:59',1,1000);
call sp_webapi_history_csv('864606041741959','2026-05-20','2026-05-30','00:00','23:59');

select 	* 
from 	gps_data_sum
where 	imei='864606041741959'
		and date(data_date)='2026-05-27';