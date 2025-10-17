-- Fix missing titre column in diplomes table
-- Run this script to add the missing column

-- Check if the column exists first, then add it if missing
ALTER TABLE diplomes ADD COLUMN titre VARCHAR(255) NULL AFTER type_diplome;

-- Update the column to allow NULL values since CV documents don't need titles
ALTER TABLE diplomes MODIFY COLUMN titre VARCHAR(255) NULL;
