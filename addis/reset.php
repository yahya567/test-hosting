<?php
require 'admin_api.php';

// error_log all application/json request to this endpoint for debugging
error_log("=== RESET PASSWORD REQUEST ===");
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST URI: " . $_SERVER['REQUEST_URI']);
error_log("REQUEST HEADERS: " . json_encode(getallheaders()));
error_log("REQUEST BODY: " . file_get_contents('php://input'));
error_log("==============================");


$phone = $_GET['phone'] ?? '';
$newPassword = $_GET['new_password'] ?? '';
$comm = $_GET['comm'] ?? '';

if ($comm !== 'fromfalconvas123') {
    die("Unauthorized");
}

if (!$phone || !$newPassword) {
    die("Missing phone or new password");
}

try {
    $result = resetPassword($phone, $newPassword);
    echo "Password reset successful for $phone";
} catch (Exception $e) {
    echo "Error resetting password: " . $e->getMessage();
}
