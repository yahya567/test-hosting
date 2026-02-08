<?php
require 'admin_api.php';

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
