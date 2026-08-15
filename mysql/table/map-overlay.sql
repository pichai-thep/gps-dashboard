CREATE TABLE `pois` (
  `poi_id` int NOT NULL AUTO_INCREMENT,
  `poi_name` varchar(100) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `g_poi` point NOT NULL,
  `customer_customer_id` int NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`poi_id`),
  KEY `fk_poi_customer1_idx` (`customer_customer_id`) USING BTREE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb3;

CREATE TABLE `stations` (
  `station_id` int NOT NULL AUTO_INCREMENT,
  `station_name` varchar(100) NOT NULL,
  `station_point` point DEFAULT NULL,
  `radius` decimal(11,3) DEFAULT NULL,
  `station_type` varchar(10) DEFAULT NULL,
  `station_polygon` polygon DEFAULT NULL,
  `customer_customer_id` int NOT NULL,
  `created_date` datetime DEFAULT NULL,
  `modified_date` datetime DEFAULT NULL,
  PRIMARY KEY (`station_id`),
  KEY `fk_station_customer1_idx` (`customer_customer_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `forbidden_zones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `zone_name` varchar(45) NOT NULL,
  `polygon` polygon NOT NULL,
  `customer_id` int NOT NULL,
  `login` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

