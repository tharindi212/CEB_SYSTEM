-- SQL script to create the quotation management system database
-- Run this in phpMyAdmin or MySQL command line

CREATE DATABASE IF NOT EXISTS quotation_system;
USE quotation_system;

-- Users table for login management
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('employee', 'clerk') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quotations table for managing quotation requests
CREATE TABLE IF NOT EXISTS quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_type VARCHAR(100) NOT NULL,
    vehicle_no VARCHAR(20) NOT NULL,
    gang VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    expected_date DATE NULL,
    attachment VARCHAR(255) NULL,
    submitted_by INT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approval_date TIMESTAMP NULL,
    clerk_notes TEXT NULL,
    -- ES / CE approvals
    officer1_approved TINYINT(1) NOT NULL DEFAULT 0,
    officer1_approved_at TIMESTAMP NULL,
    officer2_approved TINYINT(1) NOT NULL DEFAULT 0,
    officer2_approved_at TIMESTAMP NULL,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);
