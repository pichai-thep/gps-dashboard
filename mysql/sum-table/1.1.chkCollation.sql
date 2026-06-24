SELECT
    TABLE_NAME,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE();

SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CHARACTER_SET_NAME,
    COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND DATA_TYPE IN ('char','varchar','text','mediumtext','longtext');
  
-- convert all of table  
ALTER TABLE station_data_13
CONVERT TO CHARACTER SET utf8mb3
COLLATE utf8mb3_general_ci;  

-- convert all of db
ALTER DATABASE gps21
CHARACTER SET utf8mb3
COLLATE utf8mb3_general_ci;