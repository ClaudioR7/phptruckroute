<?php
require_once __DIR__ . '/../includes/config.php';
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: ' . APP_URL . '/index.php');
exit;
