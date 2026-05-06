<?php
// ============================================================
//  CareConnect – Doctor Schedules Real-Time API
//  Endpoint: /public/api/doctor_schedules.php
//  Returns ALL upcoming schedule slots for a given doctor
// ============================================================

// Buffer output so stray PHP errors don't corrupt JSON
ob_start();

require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

// Discard any stray output from includes before we send JSON
ob_clean();
header('Content-Type: application/json; charset=utf-8');

$doctorId = (int) ($_GET['doctor_id'] ?? 0);

if (!$doctorId) {
    echo json_encode(['success' => false, 'error' => 'Doctor ID diperlukan.']);
    exit;
}

try {
    $pdo = db();

    // ----------------------------------------------------------------
    // 1. Check if doctor has specific date overrides (doctor_schedule_dates)
    //    These are date-specific schedules the doctor configures manually
    // ----------------------------------------------------------------
    $hasDatesTable = false;
    try {
        $pdo->query("SELECT 1 FROM doctor_schedule_dates LIMIT 1");
        $hasDatesTable = true;
    } catch (Exception $e) {
        $hasDatesTable = false;
    }

    // ----------------------------------------------------------------
    // 2. Fetch recurring weekly schedule settings
    // ----------------------------------------------------------------
    $schedStmt = $pdo->prepare(
        "SELECT day_of_week, start_time, end_time, slot_duration 
         FROM doctor_schedules 
         WHERE doctor_id = ? AND is_active = 1 
         ORDER BY day_of_week ASC"
    );
    $schedStmt->execute([$doctorId]);
    $schedules = $schedStmt->fetchAll();

    // Build schedule map by day_of_week (only if no specific date overrides exist)
    $schedMap = [];
    foreach ($schedules as $s) {
        $schedMap[(int)$s['day_of_week']] = $s;
    }

    // ----------------------------------------------------------------
    // 3. Fetch specific date overrides (if table exists)
    // ----------------------------------------------------------------
    $specificDates = [];
    if ($hasDatesTable) {
        $sdStmt = $pdo->prepare(
            "SELECT schedule_date, start_time, end_time, slot_duration, is_closed
             FROM doctor_schedule_dates
             WHERE doctor_id = ? AND schedule_date >= CURDATE() AND schedule_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY schedule_date ASC"
        );
        $sdStmt->execute([$doctorId]);
        foreach ($sdStmt->fetchAll() as $row) {
            $specificDates[$row['schedule_date']] = $row;
        }
    }

    // ----------------------------------------------------------------
    // 4. Generate slots for the next 30 days
    // ----------------------------------------------------------------
    $days = [];
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    $dayNames   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    for ($i = 0; $i < 30; $i++) {
        $date = clone $today;
        $date->modify("+$i day");

        $dayOfWeek = (int) $date->format('w'); // 0=Sun, 6=Sat
        $dateStr   = $date->format('Y-m-d');

        $sched = null;

        // Check specific date override first
        if (isset($specificDates[$dateStr])) {
            $override = $specificDates[$dateStr];
            if ($override['is_closed']) {
                continue; // Doctor marked this date as closed
            }
            $sched = [
                'start_time'    => $override['start_time'],
                'end_time'      => $override['end_time'],
                'slot_duration' => (int) $override['slot_duration'],
            ];
        } elseif (isset($schedMap[$dayOfWeek])) {
            $sched = $schedMap[$dayOfWeek];
        } else {
            continue; // No schedule for this day
        }

        // Generate time slots
        $start    = strtotime($dateStr . ' ' . $sched['start_time']);
        $end      = strtotime($dateStr . ' ' . $sched['end_time']);
        $duration = (int) $sched['slot_duration'];

        if ($duration <= 0) $duration = 30;

        $slots = [];
        for ($t = $start; $t < $end; $t += $duration * 60) {
            // Skip past time slots for today
            if ($i === 0 && $t < time()) continue;
            $slots[] = date('H:i', $t);
        }

        if (empty($slots)) continue;

        // Get booked slots for this date
        $bookedStmt = $pdo->prepare(
            "SELECT TIME_FORMAT(scheduled_at, '%H:%i') AS slot_time 
             FROM appointments 
             WHERE doctor_id = ? AND DATE(scheduled_at) = ? AND status NOT IN ('cancelled')"
        );
        $bookedStmt->execute([$doctorId, $dateStr]);
        $bookedTimes = array_column($bookedStmt->fetchAll(), 'slot_time');

        $daySlots = [];
        foreach ($slots as $slot) {
            $daySlots[] = [
                'time'      => $slot,
                'available' => !in_array($slot, $bookedTimes, true),
            ];
        }

        $days[] = [
            'date'      => $dateStr,
            'day_name'  => $dayNames[$dayOfWeek],
            'day_num'   => $date->format('d'),
            'month'     => $monthNames[(int)$date->format('n')],
            'is_today'  => $i === 0,
            'slots'     => $daySlots,
        ];
    }

    echo json_encode([
        'success' => true,
        'days'    => $days,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();
    error_log('doctor_schedules.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Gagal memuat jadwal dokter. Silakan coba lagi.']);
}
