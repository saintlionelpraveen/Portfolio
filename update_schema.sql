-- update_schema.sql

USE praveen;

-- Site Content Table
CREATE TABLE IF NOT EXISTS site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(50) NOT NULL UNIQUE,
    content_value TEXT,
    description VARCHAR(255)
);

-- Internships Table
CREATE TABLE IF NOT EXISTS internships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    description TEXT,
    company_logo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed Data
INSERT INTO site_content (content_key, content_value, description) VALUES
('navbar_logo', 'Praveen', 'Logo text in navigation bar'),
('hero_badge_1', 'UI/UX Designer', 'Text for first floating badge'),
('hero_badge_2', 'Webflow Developer', 'Text for second floating badge'),
('hero_badge_3', 'Product Designer', 'Text for third floating badge'),
('hire_me_text', 'Hire Me!', 'Text on the CTA button'),
('clients_count', '1K+ Clients', 'Client count text'),
('clients_subtext', 'Worldwide', 'Subtext below client count')
ON DUPLICATE KEY UPDATE content_value = VALUES(content_value);
