<?php
require 'config.php';

/**
 * Obtain a fresh admin token from MediaCMS API.
 * Throws Exception if login fails, with detailed debug info.
 */
function getAdminToken() {
    $url = MEDIACMS_BASE . '/api/v1/login';

    // Use URL-encoded form instead of JSON
    $postData = http_build_query([
        'username' => ADMIN_USER,
        'password' => ADMIN_PASS
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HEADER => true, // for debug logs
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    // Debug logging
    error_log("=== ADMIN LOGIN DEBUG ===");
    error_log("POST URL: $url");
    error_log("POST DATA: $postData");
    error_log("HTTP CODE: $httpCode");
    if ($curlErr) error_log("CURL ERROR: $curlErr");
    error_log("RESPONSE HEADERS: " . $headers);
    error_log("RESPONSE BODY: " . $body);
    error_log("========================");

    $data = json_decode($body, true);
    if (!isset($data['token'])) {
        throw new Exception("Failed to obtain admin token. See PHP error log for details.");
    }

    return $data['token'];
}

/**
 * Create a new user via admin API
 */
function createUser($username, $password, $email, $name = null) {
    $token = getAdminToken();
    if (!$name) $name = $username; // fallback

    $url = MEDIACMS_BASE . '/api/v1/users/';
    $payload = json_encode([
        "username" => $username,
        "password" => $password,
        "email" => $email,
        "name" => $name
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $token",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    error_log("=== CREATE USER DEBUG ===");
    error_log("POST URL: $url");
    error_log("TOKEN: $token");
    error_log("POST PAYLOAD: $payload");
    error_log("HTTP CODE: $httpCode");
    if ($curlErr) error_log("CURL ERROR: $curlErr");
    error_log("RESPONSE HEADERS: " . $headers);
    error_log("RESPONSE BODY: " . $body);
    error_log("========================");

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Create user failed with HTTP $httpCode. See PHP error log for details.");
    }

    return json_decode($body, true);
}

/**
 * Reset password for an existing user via admin API
 */
function resetPassword($username, $newPassword) {
    $token = getAdminToken();

    $url = MEDIACMS_BASE . "/api/v1/users/$username";
    $payload = json_encode(["password" => $newPassword]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $token",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    error_log("=== RESET PASSWORD DEBUG ===");
    error_log("PUT URL: $url");
    error_log("TOKEN: $token");
    error_log("PAYLOAD: $payload");
    error_log("HTTP CODE: $httpCode");
    if ($curlErr) error_log("CURL ERROR: $curlErr");
    error_log("RESPONSE HEADERS: " . $headers);
    error_log("RESPONSE BODY: " . $body);
    error_log("============================");

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Reset password failed with HTTP $httpCode. See PHP error log for details.");
    }

    return json_decode($body, true);
}

function deleteUser($username) {
    $token = getAdminToken();

    $url = MEDIACMS_BASE . "/api/v1/users/$username";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $token",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    error_log("=== DELETE USER DEBUG ===");
    error_log("DELETE URL: $url");
    error_log("TOKEN: $token");
    error_log("HTTP CODE: $httpCode");
    if ($curlErr) error_log("CURL ERROR: $curlErr");
    error_log("RESPONSE HEADERS: " . $headers);
    error_log("RESPONSE BODY: " . $body);
    error_log("==========================");

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Delete user failed with HTTP $httpCode. See PHP error log for details.");
    }

    return json_decode($body, true);
}
