-- ============================================================
--  CareConnect – Database Schema
--  Engine : InnoDB | Charset : utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS careconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE careconnect;

-- -------------------------------------------------------
-- 1. USERS  (unified table, role-discriminated)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)    NOT NULL,
    email           VARCHAR(255)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    email_verified  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. PATIENT PROFILES
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS patient_profiles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL UNIQUE,
    nik         VARCHAR(20),
    birth_date  DATE,
    gender      ENUM('male','female','other'),
    phone       VARCHAR(20),
    address     TEXT,
    blood_type  VARCHAR(5),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. DOCTOR PROFILES
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctor_profiles (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL UNIQUE,
    specialization  VARCHAR(100),
    license_number  VARCHAR(50),
    phone           VARCHAR(20),
    bio             TEXT,
    is_available    TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. APPOINTMENTS / RESERVASI
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      BIGINT UNSIGNED NOT NULL,
    doctor_id       BIGINT UNSIGNED NOT NULL,
    scheduled_at    DATETIME        NOT NULL,
    duration_min    SMALLINT        NOT NULL DEFAULT 30,
    type            VARCHAR(60)     NOT NULL DEFAULT 'Initial Consult',
    status          ENUM('waiting','in_session','finished','cancelled') NOT NULL DEFAULT 'waiting',
    reason          TEXT,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_patient (patient_id),
    INDEX idx_doctor  (doctor_id),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 5. MEDICAL RECORDS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS medical_records (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  BIGINT UNSIGNED NOT NULL,
    patient_id      BIGINT UNSIGNED NOT NULL,
    doctor_id       BIGINT UNSIGNED NOT NULL,
    diagnosis       TEXT,
    treatment       TEXT,
    prescription    TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id)     REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)      REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 6. ADMIN OTP  (2-FA for admin login)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_otps (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    otp_hash    VARCHAR(255)    NOT NULL,
    expires_at  DATETIME        NOT NULL,
    used        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_otp (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 7. LOGIN ATTEMPTS  (rate-limiting)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier  VARCHAR(255) NOT NULL,   -- email or IP
    ip_address  VARCHAR(45)  NOT NULL,
    success     TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ident (identifier),
    INDEX idx_ip    (ip_address),
    INDEX idx_time  (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 8. AUDIT LOGS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED,
    action      VARCHAR(100) NOT NULL,
    target      VARCHAR(100),
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_action (action),
    INDEX idx_time   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 9. DEMO SEED DATA
-- -------------------------------------------------------
-- Passwords are:  Admin123!  |  Doctor123!  |  Patient123!
INSERT INTO users (name, email, password_hash, role, is_active, email_verified) VALUES
('Admin Utama',    'admin@careconnect.id',    '$2y$12$YkQz3gT4vF.kpHPZG3QMbOWJalXdR3f9gSGPbPc0jXkFuEVoLc5F2', 'admin',   1, 1),
('dr. Andi Wijaya','dr.andi@careconnect.id',  '$2y$12$kZ5Ts0y1O5lJkKpDEq0.EuVlGt6L5YJUfSmQJjHI8eIXnCp0SxG0i', 'doctor',  1, 1),
('Sarah Maulida',  'sarah@careconnect.id',    '$2y$12$8GRhY2vIqLMNpjZ3W5A7OuTqU1FPmKHXsJhBGLbq5D2TXaE9Nf.i2', 'patient', 1, 1);

INSERT INTO doctor_profiles (user_id, specialization, license_number, phone) VALUES
(2, 'Poli Umum', 'STR-12345678', '0812-0000-0001');

INSERT INTO patient_profiles (user_id, gender, phone) VALUES
(3, 'female', '0812-0000-0002');

INSERT INTO appointments (patient_id, doctor_id, scheduled_at, type, status, reason) VALUES
(3, 2, DATE_ADD(NOW(), INTERVAL 1 HOUR),  'Initial Consult', 'waiting',    'Pemeriksaan rutin'),
(3, 2, DATE_ADD(NOW(), INTERVAL 2 HOUR),  'Follow-up',       'waiting',    'Kontrol tekanan darah'),
(3, 2, DATE_SUB(NOW(), INTERVAL 1 DAY),   'Checkup',         'finished',   'Cek kolesterol');
