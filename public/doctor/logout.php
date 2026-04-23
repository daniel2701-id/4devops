<?php
require_once __DIR__ . '/../../includes/functions.php';
logout();
header('Location: ' . APP_URL . '/doctor/login.php');
exit;
