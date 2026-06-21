-- Digital Inclusion Assessment System schema (MySQL 8).
-- The `digital_inclusion` database and the `di_app` user are provisioned
-- during environment setup (see README). This script creates the tables;
-- `di_app` holds privileges only on this database, so no CREATE DATABASE here.

-- One row per survey attempt. Anonymous: no phone number stored.
CREATE TABLE IF NOT EXISTS responses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  VARCHAR(64) NOT NULL,
    consented   TINYINT(1) NOT NULL DEFAULT 1,   -- 0 = declined consent
    cell        VARCHAR(20) NULL,                -- Q1: Kamashashi/Nonko/Rwimbogo
    q2 TINYINT NULL, q3 TINYINT NULL, q4 TINYINT NULL,
    q5 TINYINT NULL, q6 TINYINT NULL, q7 TINYINT NULL, q8 TINYINT NULL,
    score       INT NULL,                        -- 0..100, NULL if declined
    category    VARCHAR(20) NULL,                -- Excluded/Low/Moderate/Included
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dashboard officials.
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
