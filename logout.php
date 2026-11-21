<?php
/**
 * StayHub User Logout
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

logoutUser();
setFlash('info', 'You have been logged out safely.');
header('Location: ' . BASE_URL . '/login.php');
exit;

