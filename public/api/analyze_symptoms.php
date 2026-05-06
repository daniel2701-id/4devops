<?php
// ============================================================
//  CareConnect – NVIDIA AI Symptom Analysis API
//  Endpoint: /public/api/analyze_symptoms.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

header('Content-Type: application/json; charset=utf-8');

// Load .env
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$apiKey  = $_ENV['NVIDIA_API_KEY'] ?? '';
$apiUrl  = $_ENV['NVIDIA_API_URL'] ?? 'https://integrate.api.nvidia.com/v1/chat/completions';
$model   = $_ENV['NVIDIA_MODEL']   ?? 'meta/llama-3.1-70b-instruct';

if (empty($apiKey) || $apiKey === 'your_nvidia_api_key_here') {
    echo json_encode(['success' => false, 'error' => 'NVIDIA API belum dikonfigurasi. Hubungi administrator.']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$symptoms = trim($input['symptoms'] ?? '');

if (empty($symptoms)) {
    echo json_encode(['success' => false, 'error' => 'Gejala tidak boleh kosong.']);
    exit;
}

if (mb_strlen($symptoms) < 10) {
    echo json_encode(['success' => false, 'error' => 'Deskripsi gejala terlalu singkat. Jelaskan lebih detail (minimal 10 karakter).']);
    exit;
}

// ---- Daftar spesialisasi yang tersedia di rumah sakit ----
$specializations = [
    'Umum',
    'Penyakit Dalam',
    'Jantung & Pembuluh Darah',
    'Anak',
    'Kandungan & Kebidanan',
    'Bedah Umum',
    'Saraf',
    'Orthopedi',
    'THT',
    'Mata',
    'Kulit & Kelamin',
    'Paru',
    'Urologi',
    'Psikiatri',
    'Gigi & Mulut',
    'Radiologi',
    'Anestesi',
    'Rehabilitasi Medik',
];

$specList = implode(', ', $specializations);

// ---- Build prompt ----
$systemPrompt = <<<PROMPT
Kamu adalah asisten medis AI di rumah sakit CareConnect. Tugas kamu:

1. VALIDASI INPUT: Periksa apakah input pengguna benar-benar berkaitan dengan gejala/keluhan kesehatan. Jika input TIDAK RELEVAN dengan medis (seperti "tes", "halo", pertanyaan di luar konteks medis, teks acak, atau pertanyaan umum), TOLAK dengan respons JSON:
{"valid": false, "error": "Input tidak valid. Silakan deskripsikan gejala atau keluhan kesehatan Anda secara jelas."}

2. Jika input VALID (berkaitan dengan gejala kesehatan), analisis gejala dan tentukan:
   - Kemungkinan penyakit/kondisi medis
   - Spesialisasi dokter yang tepat HANYA dari daftar berikut: {$specList}
   - JANGAN merekomendasikan spesialisasi di luar daftar tersebut
   - Jika gejala tidak cocok dengan spesialisasi manapun, rekomendasikan "Umum"

3. Berikan respons dalam format JSON SAJA (tanpa markdown, tanpa backtick):
{"valid": true, "disease": "Nama penyakit/kondisi", "description": "Penjelasan singkat tentang kondisi dalam 1-2 kalimat", "specialization": "Nama Spesialisasi", "urgency": "low/medium/high", "advice": "Saran singkat untuk pasien"}

PENTING:
- Respons HARUS berupa JSON valid tanpa tambahan teks apapun
- Spesialisasi HARUS ada dalam daftar yang diberikan
- Panduan Pemetaan Spesialisasi:
  * Sakit kepala, migrain, vertigo, kejang, stroke -> Saraf
  * Demam biasa, batuk, pilek ringan -> Umum
  * Nyeri lambung, maag, diabetes -> Penyakit Dalam
  * Masalah tulang, sendi, patah tulang -> Orthopedi
  * Masalah kulit, gatal, ruam -> Kulit & Kelamin
- Gunakan bahasa Indonesia
- Jangan menambahkan backtick atau markdown formatting
PROMPT;

$payload = [
    'model'    => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => "Gejala saya: " . $symptoms],
    ],
    'temperature'   => 0.3,
    'max_tokens'    => 500,
    'top_p'         => 0.9,
];

// ---- Call NVIDIA API ----
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
// curl_close not needed in PHP 8+; handle is auto-released

if ($curlErr) {
    error_log("NVIDIA API cURL error: $curlErr");
    echo json_encode(['success' => false, 'error' => 'Gagal menghubungi layanan AI. Silakan coba lagi.']);
    exit;
}

if ($httpCode !== 200) {
    error_log("NVIDIA API HTTP $httpCode: $response");
    echo json_encode(['success' => false, 'error' => 'Layanan AI sedang tidak tersedia (HTTP ' . $httpCode . '). Silakan coba lagi nanti.']);
    exit;
}

$data = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? '';

// Extract JSON block in case AI added conversational text
if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
    $content = $matches[0];
}

$result = json_decode($content, true);

if (!$result || json_last_error() !== JSON_ERROR_NONE) {
    error_log("NVIDIA API invalid JSON response: $content");
    echo json_encode(['success' => false, 'error' => 'Gagal memproses respons AI. Silakan coba lagi.']);
    exit;
}

// Check if AI rejected the input
if (isset($result['valid']) && $result['valid'] === false) {
    echo json_encode([
        'success' => false,
        'error'   => $result['error'] ?? 'Input tidak valid. Silakan deskripsikan gejala kesehatan Anda.',
    ]);
    exit;
}

// Validate specialization is in our list
if (!empty($result['specialization']) && !in_array($result['specialization'], $specializations)) {
    // Fallback to Umum if AI returns unknown specialization
    $result['specialization'] = 'Umum';
}

echo json_encode([
    'success'        => true,
    'disease'        => $result['disease'] ?? 'Tidak teridentifikasi',
    'description'    => $result['description'] ?? '',
    'specialization' => $result['specialization'] ?? 'Umum',
    'urgency'        => $result['urgency'] ?? 'medium',
    'advice'         => $result['advice'] ?? '',
]);
