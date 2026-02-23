-- Migration: add profile picture support to users table
-- Stores binary image data for user profile pictures

ALTER TABLE `users` ADD COLUMN `profile_picture` LONGBLOB DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `profile_picture_type` VARCHAR(50) DEFAULT NULL;
