<?php
require 'admin_api.php';

$phone = $_GET['phone'] ?? '';
$comm = $_GET['comm'] ?? '';
$email = $phone . '@yetemare.com'; // Generate email from phone for simplicity

if ($comm !== 'fromfalconvas123') {
    die("Unauthorized");
}

if (!$phone || !$comm) {
    die("All fields are required");
}

try {
    // Use phone as default name
    $result = deleteUser($phone);
    echo "User deleted successfully: $phone";
} catch (Exception $e) {
    echo "Error deleting user: " . $e->getMessage();
}