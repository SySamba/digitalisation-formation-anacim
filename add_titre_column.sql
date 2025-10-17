-- Add missing titre column to diplomes table
-- Copy and paste this command in phpMyAdmin SQL tab

ALTER TABLE diplomes ADD COLUMN titre VARCHAR(255) NULL AFTER type_diplome;
