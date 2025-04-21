-- Luna Chatbot Database Migrations
-- This script creates all necessary tables for the system

-- Create database (uncomment if needed)
-- CREATE DATABASE luna_chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE luna_chatbot;

-- Create prompt_data table
CREATE TABLE IF NOT EXISTS `prompt_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` TEXT NOT NULL,
  `answer` TEXT NOT NULL,
  `tags` TEXT,
  `is_trained` BOOLEAN DEFAULT 0,
  `confidence_level` FLOAT DEFAULT 1.0,
  `status` ENUM('active','inactive','draft') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_is_trained` (`is_trained`),
  FULLTEXT INDEX `idx_question` (`question`),
  FULLTEXT INDEX `idx_answer` (`answer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create response_logs table
CREATE TABLE IF NOT EXISTS `response_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_message` TEXT NOT NULL,
  `ai_response` TEXT NOT NULL,
  `source` ENUM('manual','gpt') NOT NULL,
  `score` FLOAT,
  `feedback` TEXT,
  `trained` BOOLEAN DEFAULT 0,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ip_address` (`ip_address`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_source` (`source`),
  INDEX `idx_trained` (`trained`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) UNIQUE NOT NULL,
  `value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create badwords table
CREATE TABLE IF NOT EXISTS `badwords` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `word` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tags table
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tag_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_tag_name` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin_users table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `last_login` TIMESTAMP NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create failed_login_attempts table
CREATE TABLE IF NOT EXISTS `failed_login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_username_time` (`username`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create password_reset_tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `used` BOOLEAN DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create rate_limiter table
CREATE TABLE IF NOT EXISTS `rate_limiter` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `hit_count` INT DEFAULT 1,
  `last_hit` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ip_time` (`ip_address`, `last_hit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `settings` (`key`, `value`) VALUES
('api_token', MD5(RAND())),
('openai_key', ''),
('gpt_model', 'gpt-4.1'),
('fallback_model', 'o4-mini'),
('fallback_response', 'Sorry, I could not process your request at this time. Please try again later.'),
('max_retries', '3'),
('rate_limit_per_minute', '10'),
('log_retention_days', '90');

-- Insert default admin user (password: admin123)
-- IMPORTANT: Change this password in production!
INSERT INTO `admin_users` (`username`, `password`, `email`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'active');

-- Insert common badwords (limited list for demo)
INSERT INTO `badwords` (`word`) VALUES
('fuck'), ('shit'), ('ass'), ('bitch'), ('dick'), ('porn'), ('xxx');

-- Create event to cleanup old logs automatically
DELIMITER //
CREATE EVENT IF NOT EXISTS `cleanup_old_logs_daily`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
  DECLARE retention_days INT;
  SELECT CAST(value AS SIGNED) INTO retention_days FROM settings WHERE `key` = 'log_retention_days';
  
  IF retention_days IS NULL THEN
    SET retention_days = 90;
  END IF;
  
  DELETE FROM response_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY);
  DELETE FROM rate_limiter WHERE last_hit < DATE_SUB(NOW(), INTERVAL 1 DAY);
  DELETE FROM failed_login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
END //
DELIMITER ;

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;