CREATE TABLE mobile_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,
    login VARCHAR(100) NOT NULL,
    server_name VARCHAR(100) NULL,

    device_id VARCHAR(191) NOT NULL,
    platform ENUM('android','ios') NOT NULL,
    push_provider ENUM('fcm','apns') NOT NULL,
    push_token TEXT NOT NULL,

    app_version VARCHAR(50) NULL,
    device_brand VARCHAR(100) NULL,
    device_model VARCHAR(150) NULL,
    os_version VARCHAR(50) NULL,

    notification_enabled TINYINT(1) NOT NULL DEFAULT 1,

    last_seen_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uk_user_device_provider (user_id, device_id, push_provider),

    KEY idx_user_id (user_id),
    KEY idx_login (login),
    KEY idx_platform (platform),
    KEY idx_push_provider (push_provider),
    KEY idx_server_name (server_name),
    KEY idx_notify_lookup (user_id, server_name, notification_enabled)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb3
COLLATE=utf8mb3_general_ci;