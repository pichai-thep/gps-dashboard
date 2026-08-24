CALL sp_webapi_current_track('test21',-1,'plate_no','asc','',0,-1,1,100);
CALL sp_webapi_history('864606041741959','2026-05-29','2026-05-29','00:00','23:59',0,1000);

CALL sp_move_gps_data_to_report(5000);
    
CALL sp_sum_report_table(0,'2026-05-25',NULL);	-- execute report by report-table-index
CALL sp_sum_gps_report_daily(DATE_SUB(CURDATE(), INTERVAL 1 DAY));
CALL sp_sum_gps_report_daily('2026-05-29');
CALL sp_run_summary_report_by_customer(123, '2026-08-01', '2026-08-07');
CALL sp_run_summary_report_by_imei('864606041741959', '2026-08-01', '2026-08-07');
CALL sp_run_station_summary_report_by_customer(123, '2026-08-01', '2026-08-07');
CALL sp_run_station_summary_report_by_imei('864606041741959', '2026-08-01', '2026-08-07');


CALL sp_report_daily_summary('sae','2026-07-30','2026-07-31','','',1,100,'distance_m','desc');


CALL sp_report_status_summary('sae','2026-05-01','2026-05-31',null,0,'','',1,100,'data_date','desc');
CALL sp_report_status_summary('sae','2026-05-01','2026-05-31','run',5,'','1,2',1,100,'data_date','desc');
CALL sp_report_status_summary('sae','2026-05-01','2026-05-31',null,0,'864606041741959,864606041747246,864022083721075,860470063304947','',1,100,'data_date','desc');

CALL sp_report_station_summary('sae','2026-05-01','2026-05-31',0,'','',1,50,'data_date','desc');
CALL sp_report_station_summary('sae','2026-05-01','2026-05-31',0,'864606041741959,864606041747246,864022083721075,860470063304947','1,2',1,50,'data_date','desc');
