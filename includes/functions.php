<?php
// ============================================================
//  CareConnect – Bootstrap  (include this at the top of every page)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

send_security_headers();
secure_session_start();

// -------------------------------------------------------
// Shared view helpers
// -------------------------------------------------------

/** Flash message: set once, show once */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function alert_html(?string $msg, string $type = 'error'): string
{
    if (!$msg) return '';
    $colors = [
        'error'   => 'bg-red-50 border-red-300 text-red-700',
        'success' => 'bg-green-50 border-green-300 text-green-700',
        'info'    => 'bg-blue-50 border-blue-300 text-blue-700',
    ];
    $cls = $colors[$type] ?? $colors['error'];
    return '<div class="' . $cls . ' border rounded-xl px-4 py-3 text-sm font-medium mb-4">' . e($msg) . '</div>';
}

/** Tailwind CDN script tag */
function tailwind_cdn(): string
{
    return '<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>';
}

/** Google Fonts: Inter + Material Symbols */
function google_fonts(): string
{
    return '
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">';
}

function tailwind_config(string $primaryColor = '#4f378a'): string
{
    return <<<JS
<style>
  body { animation: globalFadeIn 0.3s ease-in-out; }
  @keyframes globalFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>
<script>
tailwind.config = {
  theme: {
    extend: {
      fontFamily: { sans: ['Inter', 'sans-serif'] },
      colors: {
        primary:         '{$primaryColor}',
        'primary-light': '#6750a4',
        'primary-fixed': '#e9ddff',
        surface:         '#fdf7ff',
        'on-surface':    '#1d1b20',
        'on-surface-variant': '#494551',
        'outline-variant':    '#cbc4d2',
        'surface-container':  '#f2ecf4',
        'surface-container-lowest': '#ffffff',
        error:           '#ba1a1a',
      },
    }
  }
}
</script>
JS;
}

function format_date(string $datetime, string $format = 'd M Y, H:i'): string
{
    return date($format, strtotime($datetime));
}

function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init  = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $init .= strtoupper($p[0] ?? '');
    }
    return $init ?: 'U';
}
