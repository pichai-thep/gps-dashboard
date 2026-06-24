SELECT CONCAT(
  'ALTER TABLE ', table_name,
  ' ADD KEY idx_summary (imei, data_date, station_data_id);'
) AS sql_cmd
FROM (
  SELECT
    t.table_name,
    MAX(CASE WHEN s.index_name = 'idx_summary' THEN 1 ELSE 0 END) AS has_idx_summary
  FROM information_schema.tables t
  LEFT JOIN information_schema.statistics s
    ON s.table_schema = t.table_schema
   AND s.table_name = t.table_name
  WHERE t.table_schema = DATABASE()
    AND t.table_name REGEXP '^station_data(_[0-9]+)?$'
  GROUP BY t.table_name
) x
WHERE has_idx_summary = 0
ORDER BY
  CASE
    WHEN table_name = 'station_data' THEN 0
    ELSE CAST(SUBSTRING_INDEX(table_name, '_', -1) AS UNSIGNED)
  END;