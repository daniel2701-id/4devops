<?php
// ============================================================
//  CareConnect – Mailer (PHPMailer-free native fallback)
//  For production: configure SMTP or swap with PHPMailer/SwiftMailer
// ============================================================

/**
 * Send a plain-text + HTML email via PHP's mail() or SMTP.
 * Returns true on success, false on failure.
 */
function send_email(string $to, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $fromEmail = MAIL_FROM;
    $fromName  = MAIL_NAME;

    if (empty($textBody)) {
        $textBody = strip_tags($htmlBody);
    }

    $boundary = md5(uniqid('', true));

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: CareConnect/1.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}--";

    $result = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

    error_log("[CareConnect Mailer] To: {$to} | Subject: {$subject} | Result: " . ($result ? 'OK' : 'FAIL'));

    return $result;
}

// -------------------------------------------------------
// Email Templates
// -------------------------------------------------------

function email_wrapper(string $content, string $title = 'CareConnect'): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.5px;">
              &#9829; CareConnect
            </h1>
            <p style="margin:6px 0 0;color:#bfdbfe;font-size:13px;">Platform Kesehatan Digital Terpercaya</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px 40px;">
            {$content}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="margin:0;color:#94a3b8;font-size:12px;">
              &copy; <?= date('Y') ?> CareConnect &bull; Jangan balas email ini secara langsung.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function send_appointment_reminder(array $appointment, array $patient): bool
{
    $docName    = e($appointment['doctor_name'] ?? 'Dokter');
    $dateStr    = date('l, d F Y', strtotime($appointment['scheduled_at']));
    $timeStr    = date('H:i', strtotime($appointment['scheduled_at'])) . ' WIB';
    $appUrl     = APP_URL;

    $content = <<<HTML
<h2 style="margin:0 0 8px;color:#1e293b;font-size:20px;font-weight:700;">Pengingat Janji Konsultasi 🔔</h2>
<p style="margin:0 0 24px;color:#475569;font-size:14px;line-height:1.6;">
  Halo <strong>{$patient['name']}</strong>, ini adalah pengingat bahwa Anda memiliki jadwal konsultasi <strong>besok</strong>.
</p>
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td style="color:#64748b;font-size:13px;padding-bottom:8px;">Dokter</td>
      <td style="color:#1e293b;font-size:13px;font-weight:700;text-align:right;">{$docName}</td>
    </tr>
    <tr>
      <td style="color:#64748b;font-size:13px;padding-bottom:8px;">Tanggal</td>
      <td style="color:#1e293b;font-size:13px;font-weight:700;text-align:right;">{$dateStr}</td>
    </tr>
    <tr>
      <td style="color:#64748b;font-size:13px;">Pukul</td>
      <td style="color:#1e293b;font-size:13px;font-weight:700;text-align:right;">{$timeStr}</td>
    </tr>
  </table>
</div>
<p style="text-align:center;">
  <a href="{$appUrl}/patient/dashboard.php" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;">Lihat Detail Reservasi</a>
</p>
HTML;

    return send_email(
        $patient['email'],
        $patient['name'],
        '⏰ Pengingat: Konsultasi Besok – ' . $appointment['doctor_name'],
        email_wrapper($content, 'Pengingat Janji')
    );
}

function send_status_notification(array $appointment, array $patient, string $newStatus): bool
{
    $statusLabels = [
        'waiting'    => ['Menunggu Konfirmasi', '🕐', '#d97706'],
        'in_session' => ['Konsultasi Dimulai',  '🩺', '#2563eb'],
        'finished'   => ['Konsultasi Selesai',  '✅', '#16a34a'],
        'cancelled'  => ['Reservasi Dibatalkan', '❌', '#dc2626'],
    ];
    [$label, $icon, $color] = $statusLabels[$newStatus] ?? ['Diperbarui', '📋', '#475569'];

    $docName = e($appointment['doctor_name'] ?? 'Dokter');
    $dateStr = date('d F Y, H:i', strtotime($appointment['scheduled_at'])) . ' WIB';
    $appUrl  = APP_URL;

    $content = <<<HTML
<h2 style="margin:0 0 8px;color:#1e293b;font-size:20px;font-weight:700;">{$icon} Status Reservasi Diperbarui</h2>
<p style="margin:0 0 24px;color:#475569;font-size:14px;line-height:1.6;">
  Halo <strong>{$patient['name']}</strong>, status reservasi konsultasi Anda dengan <strong>{$docName}</strong> telah diperbarui.
</p>
<div style="background:#f8fafc;border-left:4px solid {$color};border-radius:0 12px 12px 0;padding:16px 20px;margin-bottom:24px;">
  <p style="margin:0;font-size:18px;font-weight:800;color:{$color};">{$label}</p>
  <p style="margin:6px 0 0;color:#64748b;font-size:13px;">{$dateStr}</p>
</div>
<p style="text-align:center;">
  <a href="{$appUrl}/patient/riwayat.php" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;">Lihat Riwayat</a>
</p>
HTML;

    return send_email(
        $patient['email'],
        $patient['name'],
        "{$icon} Status Reservasi: {$label}",
        email_wrapper($content, 'Update Status Reservasi')
    );
}

function send_password_reset_email(string $toEmail, string $toName, string $resetUrl): bool
{
    $content = <<<HTML
<h2 style="margin:0 0 8px;color:#1e293b;font-size:20px;font-weight:700;">Reset Password 🔑</h2>
<p style="margin:0 0 24px;color:#475569;font-size:14px;line-height:1.6;">
  Halo <strong>{$toName}</strong>, kami menerima permintaan untuk mereset password akun CareConnect Anda.
  Klik tombol di bawah untuk membuat password baru. Link ini <strong>berlaku 15 menit</strong>.
</p>
<p style="text-align:center;margin-bottom:24px;">
  <a href="{$resetUrl}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-weight:700;font-size:14px;">Reset Password Saya</a>
</p>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;">
  <p style="margin:0;color:#991b1b;font-size:12px;line-height:1.5;">
    ⚠️ Jika Anda tidak meminta reset password, abaikan email ini. Password Anda tetap aman.
  </p>
</div>
HTML;

    return send_email(
        $toEmail,
        $toName,
        '🔑 Reset Password CareConnect',
        email_wrapper($content, 'Reset Password')
    );
}

// -------------------------------------------------------
// Notification DB helpers
// -------------------------------------------------------

function create_notification(int $userId, string $type, string $title, string $message, ?int $relatedId = null): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            "INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)"
        )->execute([$userId, $type, $title, $message, $relatedId]);
    } catch (Exception $e) {
        error_log('create_notification error: ' . $e->getMessage());
    }
}

function get_unread_count(int $userId): int
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
