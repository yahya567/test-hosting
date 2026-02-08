<?php
require 'admin_api.php';

// echo empty if no proper request is made, but log all requests for debugging
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("=== INVALID REQUEST METHOD ===");
    error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("REQUEST URI: " . $_SERVER['REQUEST_URI']);
    error_log("REQUEST HEADERS: " . json_encode(getallheaders()));
    error_log("REQUEST BODY: " . file_get_contents('php://input'));
    error_log("==============================");
    die("Silence is golden");
}

// error_log all application/json request to this endpoint for debugging
error_log("=== RESET PASSWORD REQUEST ===");
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST URI: " . $_SERVER['REQUEST_URI']);
error_log("REQUEST HEADERS: " . json_encode(getallheaders()));
error_log("REQUEST BODY: " . file_get_contents('php://input'));
error_log("==============================");

// we have the following requests for different scenarios
// {"id":"97911e4d-0ca5-482a-98fe-549cfe2c0cad","type":"password.reset","created_at":"2026-02-08T14:46:51.439145Z","data":{"msisdn":"+251911166183","service_name":"Yetemare","timestamp":"2026-02-08T14:46:51.439850Z","medium":"SDP_API","password":"1828"}}
// {"id":"b9327c3c-7968-4ca5-b66f-069f31e5128c","type":"subscription.created","created_at":"2026-02-08T14:45:41.348090Z","data":{"msisdn":"+251911166183","service_name":"Yetemare","timestamp":"2026-02-08T14:45:41.348512Z","medium":"SDP_API","password":9384}}
// {"id":"3c080362-d2e0-4d0a-be94-83725913e811","type":"subscription.cancelled","created_at":"2026-02-08T14:43:57.747762Z","data":{"msisdn":"+251911166183","service_name":"Yetemare","timestamp":"2026-02-08T14:43:57.748289Z","medium":"SDP_API","password":null}}

// let's process each request type accordingly. For password.reset, we will call resetPassword function from admin_api.php
$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true);
if (!$requestData || !isset($requestData['type']) || !isset($requestData['data'])) {
    die("Invalid request format");
}

$type = $requestData['type'];
$data = $requestData['data'];
$phone = isset($data['msisdn']) ? ltrim($data['msisdn'], '+') : '';
$password = $data['password'] ?? '';

if ($type === 'password.reset') {
    try {
        $result = resetPassword($phone, $password);
        error_log("Password reset successful for $phone");
    } catch (Exception $e) {
        error_log("Error resetting password for $phone: " . $e->getMessage());
    }
} elseif ($type === 'subscription.created') {
    // For subscription.created
    try {
        $email = $phone . '@yetemare.com'; // Generate email from phone for simplicity
        $result = createUser($phone, $password, $email, $phone);
        error_log("User created successfully: $phone");
    } catch (Exception $e) {
        error_log("Error creating user for $phone: " . $e->getMessage());
    }
} elseif ($type === 'subscription.cancelled') {
    // For subscription.cancelled
    try {
        $result = deleteUser($phone);
        error_log("User deleted successfully: $phone");
    } catch (Exception $e) {
        error_log("Error deleting user for $phone: " . $e->getMessage());
    }
} else {
    // For other types, we can just log them for now
    error_log("Received unsupported request type: $type");
}
