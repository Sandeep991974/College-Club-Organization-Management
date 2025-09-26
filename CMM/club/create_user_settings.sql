-- Create user_settings table for storing user preferences
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email_notifications` tinyint(1) DEFAULT 1,
    `event_reminders` tinyint(1) DEFAULT 1,
    `club_updates` tinyint(1) DEFAULT 1,
    `marketing_emails` tinyint(1) DEFAULT 0,
    `profile_visibility` enum('public','members','private') DEFAULT 'public',
    `show_email` tinyint(1) DEFAULT 0,
    `show_phone` tinyint(1) DEFAULT 0,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    KEY `user_settings_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add status column to users table if it doesn't exist
ALTER TABLE `users` ADD COLUMN `status` enum('active','inactive') DEFAULT 'active';