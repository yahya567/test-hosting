<?php
require 'admin_api.php';

$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';
$email = $_POST['email'] ?? '';

if (!$phone || !$password || !$email) {
    die("All fields are required");
}

try {
    // Use phone as default name
    $result = createUser($phone, $password, $email, $phone);
    echo "User created successfully: $phone";
} catch (Exception $e) {
    echo "Error creating user: " . $e->getMessage();
}