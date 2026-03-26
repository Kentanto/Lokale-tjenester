-- Migration: Add bio/description field to users table
ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL;
