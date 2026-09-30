-- Apply before deploying the updated sp_sum_report_table_core procedure.
ALTER TABLE gps_sum_data
  ADD COLUMN fuel_litre decimal(6,2) DEFAULT NULL AFTER distance_withid_m,
  ADD COLUMN fuel_money decimal(9,2) DEFAULT NULL AFTER fuel_litre;
