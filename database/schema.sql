-- eSIM and Virtual Phone Number Sales Platform: spacechip
-- MySQL Database Schema

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- 1. Authentication & Users
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `is_verified` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,
    `two_fa_enabled` BOOLEAN DEFAULT FALSE,
    `two_fa_secret` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('customer', 'admin') DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id` CHAR(36) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_sessions_token_hash` (`token_hash`),
    KEY `idx_sessions_user_id` (`user_id`),
    CONSTRAINT `fk_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id` CHAR(36) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `is_used` BOOLEAN DEFAULT FALSE,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_password_reset_token_hash` (`token_hash`),
    KEY `idx_password_reset_user_id` (`user_id`),
    CONSTRAINT `fk_password_reset_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id` CHAR(36) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `is_used` BOOLEAN DEFAULT FALSE,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email_verif_token_hash` (`token_hash`),
    KEY `idx_email_verif_user_id` (`user_id`),
    CONSTRAINT `fk_email_verif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- 2. Catalog
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS `products` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('esim', 'virtual_number') NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `product_id` CHAR(36) NOT NULL,
    `sku` VARCHAR(100) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `price_usd` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `metadata` JSON DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_product_variants_sku` (`sku`),
    KEY `idx_product_variants_product_id` (`product_id`),
    CONSTRAINT `fk_product_variants_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- 3. Orders & Payments
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS `orders` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id` CHAR(36) NOT NULL,
    `order_number` VARCHAR(50) NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL,
    `status` ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_orders_number` (`order_number`),
    KEY `idx_orders_user_id` (`user_id`),
    CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `order_id` CHAR(36) NOT NULL,
    `product_variant_id` CHAR(36) NOT NULL,
    `quantity` INT DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `fulfillment_status` ENUM('pending', 'fulfilled', 'failed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order_id` (`order_id`),
    KEY `idx_order_items_variant_id` (`product_variant_id`),
    CONSTRAINT `fk_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_variant_id` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `order_id` CHAR(36) NOT NULL,
    `method` ENUM('card', 'crypto') NOT NULL,
    `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL,
    `provider_ref` VARCHAR(255) DEFAULT NULL,
    `provider_tx_id` VARCHAR(255) DEFAULT NULL,
    `provider_payload` JSON DEFAULT NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_payments_order_id` (`order_id`),
    CONSTRAINT `fk_payments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_logs` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `payment_id` CHAR(36) NOT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `payload` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payment_logs_payment_id` (`payment_id`),
    CONSTRAINT `fk_payment_logs_payment_id` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- 4. Fulfillment
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS `esim_allocations` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `order_item_id` CHAR(36) NOT NULL,
    `iccid` VARCHAR(50) NOT NULL,
    `qr_code_url` TEXT DEFAULT NULL,
    `activation_code` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'activated', 'expired', 'failed') DEFAULT 'pending',
    `provider` VARCHAR(100) DEFAULT NULL,
    `provider_response` JSON DEFAULT NULL,
    `activated_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_esim_allocations_order_item_id` (`order_item_id`),
    UNIQUE KEY `idx_esim_allocations_iccid` (`iccid`),
    CONSTRAINT `fk_esim_allocations_order_item_id` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `virtual_number_allocations` (
    `id` CHAR(36) NOT NULL DEFAULT (UUID()),
    `order_item_id` CHAR(36) NOT NULL,
    `phone_number` VARCHAR(30) NOT NULL,
    `country_code` VARCHAR(5) DEFAULT NULL,
    `status` ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
    `provider` VARCHAR(100) DEFAULT NULL,
    `provider_response` JSON DEFAULT NULL,
    `provisioned_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_vnumber_allocations_order_item_id` (`order_item_id`),
    KEY `idx_vnumber_allocations_phone_number` (`phone_number`),
    CONSTRAINT `fk_vnumber_allocations_order_item_id` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
