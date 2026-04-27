-- ============================================================
--  CareConnect – Ensure All Required Tables Exist
--  Jalankan file ini di phpMyAdmin / MySQL CLI untuk
--  memastikan semua tabel sudah ada di database produksi.
--  Aman dijalankan berulang (IF NOT EXISTS / IF NOT EXISTS)
-- ============================================================

USE careconnect;

-- F2. NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            VARCHAR(60) NOT NULL DEFAULT 'info',
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    related_id      BIGINT UNSIGNED NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    sent_email      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_notif (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- F4. REVIEWS / RATING DOKTER
CREATE TABLE IF NOT EXISTS reviews (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  BIGINT UNSIGNED NOT NULL UNIQUE,
    patient_id      BIGINT UNSIGNED NOT NULL,
    doctor_id       BIGINT UNSIGNED NOT NULL,
    rating          TINYINT NOT NULL,
    comment         TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)      REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_reviews (doctor_id),
    INDEX idx_patient_reviews (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- F6. FAMILY MEMBERS
CREATE TABLE IF NOT EXISTS family_members (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(150)    NOT NULL,
    relationship    VARCHAR(60)     NOT NULL,
    birth_date      DATE,
    gender          ENUM('male','female','other'),
    nik             VARCHAR(20),
    phone           VARCHAR(20),
    blood_type      VARCHAR(5),
    notes           TEXT,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_family (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- F7. MESSAGES (Chat)
CREATE TABLE IF NOT EXISTS messages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  BIGINT UNSIGNED NOT NULL,
    sender_id       BIGINT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    read_at         DATETIME NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)      REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_appt_msg (appointment_id),
    INDEX idx_sender_msg (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambah kolom family_member_id ke appointments (jika belum ada)
ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS family_member_id BIGINT UNSIGNED NULL AFTER patient_id;

-- Tambah foreign key jika belum ada (abaikan error jika sudah ada)
ALTER TABLE appointments
    ADD CONSTRAINT fk_appt_family FOREIGN KEY (family_member_id)
    REFERENCES family_members(id) ON DELETE SET NULL;
