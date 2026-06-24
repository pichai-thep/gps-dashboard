select t.tracker_model, t.plate_no, g.*
from gps_data g left join tracker t on g.box_imei=t.imei
where address_resolved_at is not null;

select t.tracker_model, t.plate_no, g.*
from gps_address_cache g left join tracker t on g.box_imei=t.imei;

truncate table gps_address_cache;