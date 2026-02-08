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
        "username" => (string)$username,
        "password" => (string)$password,
        "email" => (string)$email,
        "name" => (string)$name
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
function resetPassword1($username, $newPassword) {
    $token = getAdminToken();
    // Per MediaCMS API (PUT /users/{username}) the request body should include
    // `action`, `username`, and `password` as JSON. Send exactly that.
    $encUser = rawurlencode((string)$username);
    // Ensure trailing slash to match MediaCMS API route (/api/v1/users/{username}/)
    $url = MEDIACMS_BASE . "/api/v1/users/$encUser/";
    $payload = json_encode([
        'action' => 'change_password',
        'username' => (string)$username,
        'password' => (string)$newPassword
    ]);

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
        throw new Exception("Reset password failed with HTTP $httpCode. See PHP error log for details: $body");
    }

    return json_decode($body, true);
}

function resetPassword($username, $newPassword) {
    $token = getAdminToken();

    // 1) Verify user exists
    $user = mediacmsUserExists($username, $token);

    if (!$user) {
        error_log("❌ MediaCMS user does not exist: $username");
        throw new Exception("MediaCMS user not found: $username");
    }

    error_log("✅ MediaCMS user found: " . json_encode($user));

    // 2) Perform reset
    $encUser = rawurlencode($username);
    $url = MEDIACMS_BASE . "/api/v1/users/$encUser";

    // 🔥 FIX: multipart/form-data instead of JSON
    $fields = [
        'action'   => 'change_password',
        'username' => $username,
        'password' => $newPassword
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $token",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS => $fields, // ← this forces multipart/form-data
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    error_log("=== RESET PASSWORD DEBUG ===");
    error_log("PUT URL: $url");
    error_log("FIELDS: " . json_encode($fields));
    error_log("HTTP CODE: $httpCode");
    error_log("RESPONSE HEADERS: $headers");
    error_log("RESPONSE BODY: $body");
    error_log("============================");

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Reset failed: HTTP $httpCode | $body");
    }

    return json_decode($body, true);
}

function mediacmsUserExists($username, $token) {
    $encUser = rawurlencode($username);
    $url = MEDIACMS_BASE . "/api/v1/users/$encUser";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $token",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    error_log("=== USER EXISTENCE CHECK ===");
    error_log("GET URL: $url");
    error_log("HTTP CODE: $httpCode");
    error_log("RESPONSE HEADERS: $headers");
    error_log("RESPONSE BODY: $body");
    error_log("============================");

    if ($httpCode === 200) {
        return json_decode($body, true); // user object
    }

    if ($httpCode === 404) {
        return false;
    }

    throw new Exception("Unexpected response checking user: HTTP $httpCode");
}

function deleteUser($username) {
    $token = getAdminToken();

    $encUser = rawurlencode((string)$username);
    $url = MEDIACMS_BASE . "/api/v1/users/$encUser";
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
