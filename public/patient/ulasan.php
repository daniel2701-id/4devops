<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');
header('Location: dashboard.php');
exit;
