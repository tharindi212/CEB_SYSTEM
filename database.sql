-- Quotation Management System Database Schema
-- Import this file into phpMyAdmin to create the database

CREATE DATABASE IF NOT EXISTS quotation_system;
USE quotation_system;

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('clerk', 'chief_clerk', 'electricity_supervisor', 'electrical_engineer', 'chief_engineer') NOT NULL,
    status ENUM('pending', 'active', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Quotation requests table
CREATE TABLE IF NOT EXISTS quotation_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clerk_id INT NOT NULL,
    request_type ENUM('vehicle_repair', 'other') NOT NULL,

    -- Vehicle repair fields
    selected_gang VARCHAR(255),
    vehicle_number VARCHAR(100),
    repair_details TEXT,

    -- Other type fields
    resource_type VARCHAR(255),
    description TEXT,

    -- Common fields
    expecting_date DATE,
    attachment VARCHAR(255),

    -- Approval workflow
    chief_clerk_status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    chief_clerk_reason TEXT,
    chief_clerk_date TIMESTAMP NULL,

    electricity_supervisor_status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    electricity_supervisor_reason TEXT,
    electricity_supervisor_date TIMESTAMP NULL,

    electrical_engineer_status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    electrical_engineer_reason TEXT,
    electrical_engineer_date TIMESTAMP NULL,

    chief_engineer_status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    chief_engineer_reason TEXT,
    chief_engineer_date TIMESTAMP NULL,

    final_status ENUM('in_progress', 'approved', 'denied') DEFAULT 'in_progress',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (clerk_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PO/Awarding letters table
CREATE TABLE IF NOT EXISTS po_awarding_letters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quotation_id INT NOT NULL,
    created_by INT NOT NULL,
    letter_details TEXT NOT NULL,

    chief_clerk_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    electricity_supervisor_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    electrical_engineer_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    chief_engineer_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    final_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (quotation_id) REFERENCES quotation_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default Chief Engineer account (password: admin123)
INSERT INTO users (full_name, email, password, role, status)
VALUES ('Chief Engineer', 'chief@electricityboard.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chief_engineer', 'active');
