-- Migration: Add bio/description field to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL;
