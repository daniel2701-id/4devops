-- ============================================================
--  CareConnect – Schema Additions (Feature Update)
--  Jalankan setelah schema.sql awal sudah ada
-- ============================================================

USE careconnect;

-- -------------------------------------------------------
-- F1. DOCTOR TIME SLOTS (Slot Waktu Dokter)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctor_schedules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id       BIGINT UNSIGNED NOT NULL,
    day_of_week     TINYINT NOT NULL COMMENT '0=Sun,1=Mon,...,6=Sat',
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    slot_duration   SMALLINT NOT NULL DEFAULT 30 COMMENT 'minutes per slot',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doc_day (doctor_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- F2. NOTIFICATIONS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            VARCHAR(60) NOT NULL DEFAULT 'info' COMMENT 'info|reminder|status_change|system',
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    related_id      BIGINT UNSIGNED NULL COMMENT 'appointment_id or other entity',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    sent_email      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_notif (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- F3. PASSWORD RESETS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_reset (email),
    INDEX idx_token (token_hash(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- F4. REVIEWS / RATING DOKTER
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  BIGINT UNSIGNED NOT NULL UNIQUE,
    patient_id      BIGINT UNSIGNED NOT NULL,
    doctor_id       BIGINT UNSIGNED NOT NULL,
    rating          TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment         TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)      REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_reviews (doctor_id),
    INDEX idx_patient_reviews (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- F5. MEDICAL ATTACHMENTS (Upload Lampiran)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS medical_attachments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id       BIGINT UNSIGNED NOT NULL,
    uploader_id     BIGINT UNSIGNED NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    file_type       VARCHAR(50)  NOT NULL COMMENT 'pdf|image',
    file_size       INT UNSIGNED NOT NULL DEFAULT 0,
    description     VARCHAR(255),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (record_id)   REFERENCES medical_records(id) ON DELETE CASCADE,
    FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_record_attach (record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Seed: Default schedule for demo doctor (Mon-Fri, 08:00-17:00)
-- -------------------------------------------------------
INSERT IGNORE INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration) VALUES
(2, 1, '08:00:00', '17:00:00', 30),
(2, 2, '08:00:00', '17:00:00', 30),
(2, 3, '08:00:00', '17:00:00', 30),
(2, 4, '08:00:00', '17:00:00', 30),
(2, 5, '08:00:00', '17:00:00', 30);

-- -------------------------------------------------------
-- F6. FAMILY MEMBERS (Anggota Keluarga)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS family_members (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL COMMENT 'owning patient account',
    name            VARCHAR(150)    NOT NULL,
    relationship    VARCHAR(60)     NOT NULL COMMENT 'anak|pasangan|orang_tua|saudara|lainnya',
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

-- Add family_member_id to appointments (nullable, NULL = booking for self)
ALTER TABLE appointments
    ADD COLUMN family_member_id BIGINT UNSIGNED NULL AFTER patient_id,
    ADD CONSTRAINT fk_appt_family FOREIGN KEY (family_member_id) REFERENCES family_members(id) ON DELETE SET NULL;

-- -------------------------------------------------------
-- F7. MESSAGES (Chat Pasien-Dokter)
-- -------------------------------------------------------
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
    INDEX idx_sender_msg (sender_id),
    INDEX idx_msg_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
