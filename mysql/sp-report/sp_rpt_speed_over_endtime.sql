Drop procedure if exists sp_rpt_speed_over_endtime;
-- --------------------------------------------------------------------------------
-- Routine DDL
-- Note: comments before and after the routine body will not be stored by the server
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `sp_rpt_speed_over_endtime`(
    in _group int,
    in _over_type varchar(6),
    in _login varchar(20),
    in _imei varchar(20),
    in _date1 varchar(16),
    in _date2 varchar(16)

)
    proc: begin



Select 	sp.id, sp.imei, t.plate_no, date_add(sp.event_time, interval 7 hour) as event_time
     , case when (sp.over_type='cloud') then t.speed_limited else null end as speed_limited
     , sp.speed, sp.lat, sp.lng, sp.hdop, sp.num_sats
     -- , if(fn_is_accuracy(t.tracker_model, sp.hdop, sp.num_sats)=1, 'ok', 'no') as accuracy
     , sp.over_type
     , date_add(sp.end_time, interval 7 hour) as end_time
     , sec_to_time(timestampdiff(second, sp.event_time, sp.end_time)) as duration
     -- , fn_geo_tambon_shp(sp.lat, sp.lng, 1) as address
     , null as address
from 	gps_speed_over sp
            inner join tracker t on sp.imei=t.imei
            inner join user_tracker ut on t.imei=ut.tracker_imei
            inner join user u on ut.user_user_id=u.user_id

            left join customer_group_tracker cgt on t.imei=cgt.imei
            left join customer_group cg on cgt.customer_group_id=cg.customer_group_id
-- left join Tambon_0010_WKB tb on mbrcontains(tb.geom, point(sp.lat, sp.lng))=1

where
    if(nullif(_group,-1) is not null, cgt.customer_group_id, -1) = if(nullif(_group,-1) is not null, _group, -1)
  and u.login = coalesce(nullif(_login,''), u.login)
  and sp.imei = coalesce(nullif(_imei,''), sp.imei)
  and (	date_format(date_add(sp.event_time, interval 7 hour), '%Y-%m-%d %H:%i') >= _date1
    and date_format(date_add(sp.event_time, interval 7 hour), '%Y-%m-%d %H:%i') <= _date2)
--             and fn_is_accuracy(t.tracker_model, sp.hdop, sp.num_sats) = coalesce(_accuracy, fn_is_accuracy(t.tracker_model, sp.hdop, sp.num_sats))
--             and sp.speed < 100
-- 			and ifnull(sp.over_type,'') = coalesce(nullif(_over_type,''), '')
  and sp.over_type = coalesce(nullif(_over_type,''), sp.over_type)

group by sp.id, sp.imei, t.plate_no, date_add(sp.event_time, interval 7 hour)
       , t.speed_limited, sp.speed, sp.lat, sp.lng, sp.hdop, sp.num_sats, sp.end_time
order by sp.event_time desc;

end
