-- Migration: add contact_info to posts table
ALTER TABLE posts ADD COLUMN contact_info VARCHAR(255) AFTER location;
