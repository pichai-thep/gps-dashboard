CREATE TABLE mobile_access_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,
    login VARCHAR(100) NOT NULL,
    server_name VARCHAR(100) NULL,

    token_hash CHAR(64) NOT NULL,

    device_id VARCHAR(191) NULL,
    platform ENUM('android','ios') NULL,
    app_version VARCHAR(50) NULL,

    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_user_id (user_id),
    KEY idx_login (login),
    KEY idx_expires_at (expires_at)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb3
COLLATE=utf8mb3_general_ci;