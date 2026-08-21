SET FOREIGN_KEY_CHECKS=0;

-- PruneBit (Non-Custodial Edition) — database schema
-- The wallets table intentionally has NO seed/key columns:
-- the server stores only a bcrypt PIN hash and derived addresses.


CREATE TABLE `admin_decrypt_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_username` varchar(64) NOT NULL,
  `wallet_id` char(64) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wallet` (`wallet_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `admin_sessions`;
CREATE TABLE `admin_sessions` (
  `token` varchar(128) NOT NULL,
  `username` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `admin_settings`;
CREATE TABLE `admin_settings` (
  `setting_key` varchar(128) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `captcha_challenges`;
CREATE TABLE `captcha_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `code` varchar(10) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `used` tinyint(1) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `changenow_exchanges`;
CREATE TABLE `changenow_exchanges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` varchar(160) NOT NULL,
  `exchange_id` varchar(120) NOT NULL,
  `from_currency` varchar(20) NOT NULL,
  `to_currency` varchar(20) NOT NULL,
  `from_amount` decimal(24,8) DEFAULT 0.00000000,
  `to_amount` decimal(24,8) DEFAULT 0.00000000,
  `payout_address` varchar(200) DEFAULT NULL,
  `payin_address` varchar(200) DEFAULT NULL,
  `payin_extra_id` varchar(120) DEFAULT NULL,
  `status` varchar(40) DEFAULT 'new',
  `rate_id` varchar(120) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wallet` (`wallet_id`),
  KEY `idx_exchange` (`exchange_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `incidents`;
CREATE TABLE `incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `login_log`;
CREATE TABLE `login_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` varchar(128) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_log_created_at` (`created_at`),
  KEY `idx_login_log_wallet_id` (`wallet_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `prices_cache`;
CREATE TABLE `prices_cache` (
  `coin` varchar(16) NOT NULL,
  `usd` decimal(24,12) NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`coin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `rate_limit_counters`;
CREATE TABLE `rate_limit_counters` (
  `rl_key` varchar(190) NOT NULL,
  `total_hits` int(11) NOT NULL DEFAULT 1,
  `reset_time` datetime NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `token` varchar(128) NOT NULL,
  `wallet_id` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`token`),
  KEY `wallet_id` (`wallet_id`),
  KEY `idx_sessions_wallet_id` (`wallet_id`),
  KEY `idx_sessions_expires_at` (`expires_at`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `wallet_id` varchar(160) DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `priority` varchar(40) NOT NULL DEFAULT 'Normal',
  `subject` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Open',
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sync_cooldown`;
CREATE TABLE `sync_cooldown` (
  `sync_key` varchar(190) NOT NULL,
  `last_run` datetime NOT NULL,
  PRIMARY KEY (`sync_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `system_status`;
CREATE TABLE `system_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(50) NOT NULL DEFAULT 'operational',
  `message` varchar(255) NOT NULL DEFAULT 'All systems operational',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` varchar(128) NOT NULL,
  `coin` varchar(32) NOT NULL,
  `tx_hash` varchar(128) NOT NULL,
  `from_address` text DEFAULT NULL,
  `to_address` text DEFAULT NULL,
  `amount` decimal(36,18) DEFAULT NULL,
  `fee` decimal(36,18) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'pending',
  `confirmations` int(11) DEFAULT 0,
  `block_height` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tx` (`wallet_id`,`coin`,`tx_hash`),
  KEY `idx_wallet` (`wallet_id`),
  KEY `idx_coin` (`coin`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `tx_tip_cache`;
CREATE TABLE `tx_tip_cache` (
  `coin` varchar(16) NOT NULL,
  `height` int(11) NOT NULL,
  `fetched_at` datetime NOT NULL,
  PRIMARY KEY (`coin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `wallet_addresses`;
CREATE TABLE `wallet_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` varchar(128) NOT NULL,
  `coin` varchar(32) NOT NULL,
  `address_type` varchar(32) DEFAULT 'P2PKH',
  `address_index` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `balance` decimal(36,18) DEFAULT 0.000000000000000000,
  `unconfirmed_balance` decimal(36,18) DEFAULT 0.000000000000000000,
  `last_synced` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wallet_coin` (`wallet_id`,`coin`),
  KEY `idx_address_type` (`address_type`),
  CONSTRAINT `wallet_addresses_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `wallets`;
CREATE TABLE `wallets` (
  `id` varchar(128) NOT NULL,
              `password_hash` varchar(128) NOT NULL,
  `last_access` datetime DEFAULT NULL,
  `transactions` text DEFAULT NULL,
  `profile` text DEFAULT NULL,
  `id_coin` varchar(32) DEFAULT NULL,
  `total_balance` decimal(36,18) DEFAULT 0.000000000000000000,
  `backup` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



SET FOREIGN_KEY_CHECKS=1;
