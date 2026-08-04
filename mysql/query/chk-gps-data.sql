call sp_movedata_report(0);
call sp_movedata_report3(0);
call sp_movedata_report_err_continue(0);

CALL sp_move_gps_data_to_report(5000);

optimize table gps_data;
select format(count(*),0) from gps_data;

select t.imei, t.plate_no, count(*) 
from gps_data g inner join tracker t on g.box_imei=t.imei group by t.imei;

select * from gps_data where box_imei='864022083721075';
select * from data_report_2 where box_imei='864022083721075';
select * from tracker where imei='864022083721075';

select format(count(*),0) from data_report_1;
select format(count(*),0) from gps_error;
select format(count(*),0) from swipe_err;
repair table data_report_42;
select format(count(*),0) from data_report_10;

select t.imei, t.tracker_model, t.export_to,  count(g.gpsdata_id) 
from gps_data g inner join tracker t on g.box_imei=t.imei
group by box_imei 
-- having count(g.gpsdata_id) > 20
order by count(g.gpsdata_id) desc;

select err_date, ip_address, port_no, imei, err_msg, stacktrace
from gps_error
order by id desc
limit 0, 1000;

select imei, port_no, err_msg, count(*)
from gps_error
where date(err_date)='2026-05-06'
group by imei, port_no, err_msg
order by imei, port_no, count(*) desc
;

delete from gps_error where err_date < date_add(now(), interval -7 day);
optimize table gps_error;



