<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');
// Fitur Keluarga telah dinonaktifkan
header('Location: dashboard.php');
exit;
