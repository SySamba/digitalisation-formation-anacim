-- Create missing centres_formation table
-- Run this in phpMyAdmin or MySQL command line

CREATE TABLE centres_formation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    type ENUM('interne', 'externe') DEFAULT 'externe',
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert the training centers
INSERT INTO centres_formation (nom, type) VALUES
('ENAC', 'externe'),
('ERNAM', 'externe'),
('ITAerea', 'externe'),
('IFURTA', 'externe'),
('EPT', 'externe'),
('IFNPC', 'externe'),
('EMAERO services', 'externe');
