-- Migration: create user daily post limit tracking
-- Tracks how many posts each user has made today

CREATE TABLE IF NOT EXISTS `user_posts_daily` (
  `user_id` INT UNSIGNED NOT NULL,
  `posts_today` INT DEFAULT 0,
  `last_reset_date` DATE NOT NULL DEFAULT (CURDATE()),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_posts_daily_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

