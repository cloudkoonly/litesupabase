CREATE TABLE users (
                       id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Auto-incrementing primary key',
                       uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Globally unique identifier (UUID)',
                       email VARCHAR(255) NOT NULL UNIQUE COMMENT 'User''s email address, unique',
                       password VARCHAR(255) COMMENT 'Encrypted password (e.g., bcrypt hash)',
                       name VARCHAR(100) COMMENT 'User''s display name (optional)',
                       refresh_token_hash VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Hashed refresh token',
                       expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Expiration timestamp for refresh token',
                       revoked BOOLEAN DEFAULT FALSE COMMENT 'Indicates if token is revoked',
                       created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when user was created',
                       updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp when user was last updated',
                       last_sign_in_at TIMESTAMP COMMENT 'Timestamp of user''s last sign-in',
                       provider VARCHAR(50) COMMENT 'Authentication provider (e.g., email, google, github)',
                       provider_id VARCHAR(255) COMMENT 'Unique ID from third-party provider',
                       metadata JSON COMMENT 'Additional user data (e.g., avatar, preferences)',
                       is_active BOOLEAN DEFAULT FALSE COMMENT 'Indicates if user account is active',
                       password_reset_token VARCHAR(255) COMMENT 'Token for password reset',
                       password_reset_expires_at TIMESTAMP COMMENT 'Expiration timestamp for password reset token',
                       INDEX idx_uuid (uuid),
                       INDEX idx_email (email),
                       INDEX idx_refresh_token_hash (refresh_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin` (
                         `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Auto-incrementing primary key',
                         `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'User''s email address, unique',
                         `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Encrypted password (e.g., bcrypt hash)',
                         `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User''s display name (optional)',
                         `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when user was created',
                         `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp when user was last updated',
                         `last_sign_in_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of user''s last sign-in',
                         `is_active` tinyint(1) DEFAULT '1' COMMENT 'Indicates if user account is active',
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
