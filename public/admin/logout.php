<?php
require_once __DIR__ . '/../../includes/functions.php';
// Clear 2FA pending data too
unset($_SESSION['admin_pending_id'], $_SESSION['admin_pending_name'],
      $_SESSION['admin_pending_email'], $_SESSION['admin_otp_demo']);
logout();
header('Location: ' . APP_URL . '/admin/login.php');
exit;
