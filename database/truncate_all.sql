-- ============================================================
--  CareConnect – TRUNCATE ALL DATA (Tanpa Drop Tabel)
--  Menghapus semua data dari setiap tabel dengan urutan
--  yang benar agar tidak melanggar foreign key constraints.
--  Jalankan script ini di phpMyAdmin atau MySQL CLI.
-- ============================================================

USE careconnect;

-- Nonaktifkan pengecekan foreign key sementara
SET FOREIGN_KEY_CHECKS = 0;

-- Hapus semua data dari setiap tabel
TRUNCATE TABLE medical_attachments;
TRUNCATE TABLE medical_records;
TRUNCATE TABLE messages;
TRUNCATE TABLE reviews;
TRUNCATE TABLE notifications;
TRUNCATE TABLE appointments;
TRUNCATE TABLE doctor_schedules;
TRUNCATE TABLE family_members;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE admin_otps;
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE doctor_profiles;
TRUNCATE TABLE patient_profiles;
TRUNCATE TABLE users;

-- Aktifkan kembali pengecekan foreign key
SET FOREIGN_KEY_CHECKS = 1;

-- Verifikasi semua tabel kosong
SELECT 'users' AS tabel, COUNT(*) AS jumlah FROM users
UNION ALL SELECT 'patient_profiles', COUNT(*) FROM patient_profiles
UNION ALL SELECT 'doctor_profiles', COUNT(*) FROM doctor_profiles
UNION ALL SELECT 'appointments', COUNT(*) FROM appointments
UNION ALL SELECT 'medical_records', COUNT(*) FROM medical_records
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'doctor_schedules', COUNT(*) FROM doctor_schedules
UNION ALL SELECT 'messages', COUNT(*) FROM messages
UNION ALL SELECT 'reviews', COUNT(*) FROM reviews
UNION ALL SELECT 'family_members', COUNT(*) FROM family_members
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs
UNION ALL SELECT 'login_attempts', COUNT(*) FROM login_attempts;
