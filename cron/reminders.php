<?php
// ============================================================
//  CareConnect – Cron Job: H-1 Appointment Reminders
//  Run daily via cron: php /path/to/careconnect/cron/reminders.php
//  Example cron entry: 0 8 * * * php /var/www/html/careconnect/cron/reminders.php
// ============================================================

define('CRON_ACCESS', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$pdo = db();

echo "[" . date('Y-m-d H:i:s') . "] CareConnect Reminder Cron Started\n";

// Find appointments scheduled for TOMORROW that haven't had a reminder sent
$stmt = $pdo->query(
    "SELECT a.id, a.scheduled_at,
            p.id AS patient_id, p.name AS patient_name, p.email AS patient_email,
            d.name AS doctor_name
     FROM appointments a
     JOIN users p ON p.id = a.patient_id
     JOIN users d ON d.id = a.doctor_id
     WHERE a.status = 'waiting'
       AND DATE(a.scheduled_at) = CURDATE() + INTERVAL 1 DAY
       AND NOT EXISTS (
           SELECT 1 FROM notifications n
           WHERE n.user_id = a.patient_id
             AND n.related_id = a.id
             AND n.type = 'reminder'
       )"
);

$appointments = $stmt->fetchAll();
echo "Found " . count($appointments) . " appointment(s) to remind.\n";

foreach ($appointments as $appt) {
    // 1. Create in-app notification
    $pdo->prepare(
        "INSERT INTO notifications (user_id, type, title, message, related_id)
         VALUES (?, 'reminder', ?, ?, ?)"
    )->execute([
        $appt['patient_id'],
        'Pengingat: Konsultasi Besok!',
        'Anda memiliki jadwal konsultasi dengan ' . $appt['doctor_name'] . ' besok pukul ' .
            date('H:i', strtotime($appt['scheduled_at'])) . ' WIB.',
        $appt['id']
    ]);

    // 2. Send email
    $patient = [
        'id'    => $appt['patient_id'],
        'name'  => $appt['patient_name'],
        'email' => $appt['patient_email'],
    ];
    $appointment = [
        'doctor_name'  => $appt['doctor_name'],
        'scheduled_at' => $appt['scheduled_at'],
    ];

    $emailSent = send_appointment_reminder($appointment, $patient);

    // 3. Mark email sent
    if ($emailSent) {
        $pdo->prepare(
            "UPDATE notifications SET sent_email = 1 WHERE user_id = ? AND related_id = ? AND type = 'reminder' ORDER BY id DESC LIMIT 1"
        )->execute([$appt['patient_id'], $appt['id']]);
    }

    echo "  Reminded: {$appt['patient_name']} ({$appt['patient_email']}) – Appt #{$appt['id']} – Email: " . ($emailSent ? 'OK' : 'FAILED') . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
