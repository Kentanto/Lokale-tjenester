-- Migration: add contact_info to posts table
ALTER TABLE posts ADD COLUMN IF NOT EXISTS contact_info VARCHAR(255) AFTER location;
