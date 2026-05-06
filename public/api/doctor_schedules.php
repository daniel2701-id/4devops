<?php
// ============================================================
//  CareConnect – Doctor Schedules Real-Time API
//  Endpoint: /public/api/doctor_schedules.php
//  Returns ALL upcoming schedule slots for a given doctor
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

header('Content-Type: application/json; charset=utf-8');

$doctorId = (int) ($_GET['doctor_id'] ?? 0);

if (!$doctorId) {
    echo json_encode(['success' => false, 'error' => 'Doctor ID diperlukan.']);
    exit;
}

$pdo = db();

// Fetch doctor schedule settings
$schedStmt = $pdo->prepare(
    "SELECT day_of_week, start_time, end_time, slot_duration 
     FROM doctor_schedules 
     WHERE doctor_id = ? AND is_active = 1 
     ORDER BY day_of_week ASC"
);
$schedStmt->execute([$doctorId]);
$schedules = $schedStmt->fetchAll();

// Default schedule if none set (Mon-Fri 08:00-16:00)
if (empty($schedules)) {
    $schedules = [];
    for ($d = 1; $d <= 5; $d++) {
        $schedules[] = [
            'day_of_week'    => $d,
            'start_time'     => '08:00:00',
            'end_time'       => '16:00:00',
            'slot_duration'  => 30,
        ];
    }
}

// Build schedule map by day_of_week
$schedMap = [];
foreach ($schedules as $s) {
    $schedMap[(int)$s['day_of_week']] = $s;
}

// Generate slots for the next 14 days
$days = [];
$today = new DateTime();
$today->setTime(0, 0, 0);

for ($i = 0; $i < 14; $i++) {
    $date = clone $today;
    $date->modify("+$i day");
    
    $dayOfWeek = (int) $date->format('w'); // 0=Sun, 6=Sat
    
    if (!isset($schedMap[$dayOfWeek])) {
        continue; // Doctor doesn't work this day
    }
    
    $sched = $schedMap[$dayOfWeek];
    $dateStr = $date->format('Y-m-d');
    
    // Generate time slots
    $start    = strtotime($dateStr . ' ' . $sched['start_time']);
    $end      = strtotime($dateStr . ' ' . $sched['end_time']);
    $duration = (int) $sched['slot_duration'];
    
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
    
    // Day name in Indonesian
    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
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
]);
