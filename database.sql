-- database.sql

-- NOTE: On shared hosting, you likely cannot create a database script.
-- Import the tables below into your existing database.
-- CREATE DATABASE IF NOT EXISTS praveen;
-- USE praveen;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255),
    tech_stack VARCHAR(255),
    github_link VARCHAR(255),
    demo_link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Skills Table
CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(50) NOT NULL,
    percentage INT NOT NULL CHECK (percentage BETWEEN 0 AND 100),
    description TEXT,
    tags VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Migration: Add description and tags columns if they don't exist
ALTER TABLE skills ADD COLUMN IF NOT EXISTS description TEXT AFTER percentage;
ALTER TABLE skills ADD COLUMN IF NOT EXISTS tags VARCHAR(255) AFTER description;

-- About Table
CREATE TABLE IF NOT EXISTS about (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    profile_image VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Site Content Table (For dynamic text)
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

-- Insert Default Site Content (Seeding)
INSERT INTO site_content (content_key, content_value, description) VALUES
('navbar_logo', 'Praveen', 'Logo text in navigation bar'),
('hero_badge_1', 'UI/UX Designer', 'Text for first floating badge'),
('hero_badge_2', 'Webflow Developer', 'Text for second floating badge'),
('hero_badge_3', 'Product Designer', 'Text for third floating badge'),
('hire_me_text', 'Hire Me!', 'Text on the CTA button'),
('clients_count', '1K+ Clients', 'Client count text'),
('clients_subtext', 'Worldwide', 'Subtext below client count')
ON DUPLICATE KEY UPDATE content_value = VALUES(content_value);

-- Hero Section Table
CREATE TABLE IF NOT EXISTS hero (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    subtitle VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Social Links Table
CREATE TABLE IF NOT EXISTS social_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon_class VARCHAR(50), -- For FontAwesome or similar
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages Table (Contact Form)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Remove existing admin user to ensure clean slate
DELETE FROM admin_users WHERE username = 'praveen';

-- Insert default admin user (password: Tebi1328)
INSERT INTO admin_users (username, password) VALUES 
('praveen', 'Tebi1328');
-- This application uses plain text passwords for admin login.
