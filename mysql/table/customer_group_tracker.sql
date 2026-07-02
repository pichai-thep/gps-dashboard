ALTER TABLE customer_group_tracker
    ADD UNIQUE KEY uk_customer_group_tracker_imei_group (
    imei,
    customer_group_id
    );