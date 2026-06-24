show processlist;

select @@GLOBAL.event_scheduler;
set @@GLOBAL.event_scheduler=ON;

alter event moveto_report disable;
create EVENT moveto_report
    ON SCHEDULE EVERY 10 minute 
		starts '2022-03-10 00:00:00'
	ON COMPLETION PRESERVE
    DO
      call sp_movedata_report(0);

alter event purge_data disable;
create event purge_data
    on schedule every '1' day
    starts '2022-03-10 00:10:00'
    on Completion preserve
    do 
        call sp_purge_data();
        
alter event summary_report enable;
create event summary_report
    on schedule every '1' day
    starts '2022-03-10 00:30:00'
    on Completion preserve
    do 
        call sp_report_summary_batch(null);

alter event purge_hh disable;
create event purge_hh
    on schedule every '1' hour
    starts '2022-03-10 16:00:00'
    on Completion preserve
    do 
        call sp_purge_hh(hour(now()));
                

alter event station_inout_batch disable;
alter event station_inout_batch
    on schedule every '1' day
    starts '2024-10-30 02:00:00'
    on Completion preserve
    do 
        call sp_rpt_station_exec(null);
        
alter event status_batch disable;
create event status_batch
    on schedule every '1' day
    starts '2022-03-10 03:00:00'
    on Completion preserve
    do 
        call sp_data_sum_batch(null);     
        
        
alter event export_active_device disable;
create event export_active_device
    on schedule every '1' day
    starts '2022-03-10 05:00:00'
    on Completion preserve
    do 
        call sp_export_active_device();
                
alter event data_report_dlt_move enable;
alter event data_report_dlt_move
    on schedule every '1' day
    starts '2025-01-27 05:00:00'
    on Completion preserve
    do 
        call sp_data_report_dlt_move();

drop event ev_sum_gps_report_daily;
drop event summary_report;
drop event station_inout_batch;
drop event status_batch;
drop event safety_batch;
drop event data_report_dlt_move;

DROP EVENT IF EXISTS ev_sum_gps_daily;

SELECT event_name, event_definition, event_type, execute_at, interval_value, INTERVAL_FIELD, starts, status, LAST_EXECUTED 
FROM INFORMATION_SCHEMA.EVENTS 
where event_schema='gpsdb' 
order by date_format(starts, '%H:%i');