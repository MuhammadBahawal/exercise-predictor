-- ============================================================
-- Fitnessgym Database Setup Script
-- Run this in phpMyAdmin or MySQL CLI to create the database
-- ============================================================

CREATE DATABASE IF NOT EXISTS Fitnessgym;
USE Fitnessgym;

-- -----------------------------------------------------------
-- Users Table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    weight DECIMAL(5,1) DEFAULT NULL,
    height DECIMAL(5,1) DEFAULT NULL,
    role ENUM('user','admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Exercises Table (admin-managed exercise library)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS Exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default exercises
INSERT IGNORE INTO Exercises (name) VALUES
('Lateral Rise'),
('Alternative Dumbbell Curls'),
('Barbell Row'),
('Push Up'),
('Squats'),
('Shoulder Press'),
('Tricep Dips');

-- -----------------------------------------------------------
-- Workout Logs Table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS WorkoutLogs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_name VARCHAR(150) NOT NULL,
    weights_lifted DECIMAL(6,1) DEFAULT 0,
    repetitions INT DEFAULT 0,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Progress Tracking Table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS progresstracking (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    body_weight DECIMAL(5,1) DEFAULT NULL,
    bmi DECIMAL(5,1) DEFAULT NULL,
    body_fat_percentage DECIMAL(5,2) DEFAULT NULL,
    lifted_weights DECIMAL(6,1) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Fitness Plans Table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS FitnessPlans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_name VARCHAR(150) NOT NULL,
    plan_details TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Food Composition Table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS foodcomposition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_name VARCHAR(150) NOT NULL,
    calories DECIMAL(8,2) DEFAULT 0,
    proteins DECIMAL(8,2) DEFAULT 0,
    carbs DECIMAL(8,2) DEFAULT 0,
    fats DECIMAL(8,2) DEFAULT 0,
    date_logged DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Streaks Table (for achievements / badges)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS streaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    current_streak INT DEFAULT 0,
    last_logged DATE DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Seed Admin User (password: admin)
-- -----------------------------------------------------------
INSERT IGNORE INTO Users (name, password, weight, height, role) 
VALUES ('admin', 'admin', NULL, NULL, 'admin');
