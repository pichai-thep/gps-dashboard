SELECT *
FROM gps_sum_log
WHERE process_date = curdate()-interval 1 day
ORDER BY start_time;

SELECT *
FROM gps_sum_data
WHERE data_date = curdate()-interval 1 day
	and imei='864022081185976'
LIMIT 200;

SELECT *
FROM gps_sum_status
WHERE data_date = curdate()-interval 1 day
order by imei, data_date, start_time
LIMIT 2000;

SELECT *
FROM gps_sum_station
WHERE data_date = curdate()-interval 1 day
order by imei, data_date, start_time
LIMIT 2000;

SELECT *
FROM gps_sum_station
-- WHERE data_date = curdate()-interval 1 day
order by imei, data_date, start_time
LIMIT 2000;