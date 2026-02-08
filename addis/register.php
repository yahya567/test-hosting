<?php
require 'admin_api.php';

$phone = $_GET['phone'] ?? '';
$password = $_GET['password'] ?? '';
$comm = $_GET['comm'] ?? '';
$email = $phone . '@yetemare.com'; // Generate email from phone for simplicity

if ($comm !== 'fromfalconvas123') {
    die("Unauthorized");
}

if (!$phone || !$password) {
    die("All fields are required");
}

try {
    // Use phone as default name
    $result = createUser($phone, $password, $email, $phone);
    echo "User created successfully: $phone";
} catch (Exception $e) {
    echo "Error creating user: " . $e->getMessage();
}