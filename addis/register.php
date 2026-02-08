<?php
require 'admin_api.php';

$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';
$email = $phone . '@yetemare.com'; // Generate email from phone for simplicity

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