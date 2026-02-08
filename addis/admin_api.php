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
function resetPassword($username, $newPassword) {
    $token = getAdminToken();
    $encUser = rawurlencode((string)$username);
    $url = MEDIACMS_BASE . "/api/v1/users/$encUser";
    $payload = json_encode(["action" => "change_password", "password" => (string)$newPassword, "username" => (string)$username]);

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

    // // If server expects multipart with a filename, retry as multipart/form-data using POST
    // if ($httpCode < 200 || $httpCode >= 300) {
    //     if ($httpCode === 400 && strpos($body, 'Missing filename') !== false) {
    //         error_log("RESET PASSWORD: Server requested filename; retrying as multipart/form-data (POST + override)");

    //         $tmp = tempnam(sys_get_temp_dir(), 'up');
    //         file_put_contents($tmp, '');
    //         $cfile = new CURLFile($tmp, 'application/octet-stream', 'empty');

    //         $postFields = [
    //             'password' => (string)$newPassword,
    //             'file' => $cfile
    //         ];

    //         $ch2 = curl_init($url);
    //         curl_setopt_array($ch2, [
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_POST => true,
    //             CURLOPT_HEADER => true,
    //             CURLOPT_HTTPHEADER => [
    //                 "Authorization: Token $token",
    //                 "Accept: application/json",
    //                 "X-HTTP-Method-Override: PUT"
    //             ],
    //             CURLOPT_POSTFIELDS => $postFields,
    //             CURLOPT_SSL_VERIFYPEER => true
    //         ]);

    //         $resp2 = curl_exec($ch2);
    //         $err2 = curl_error($ch2);
    //         $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    //         $hsize2 = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
    //         $h2 = substr($resp2, 0, $hsize2);
    //         $b2 = substr($resp2, $hsize2);
    //         curl_close($ch2);
    //         @unlink($tmp);

    //         error_log("=== RESET PASSWORD RETRY DEBUG ===");
    //         error_log("HTTP CODE: $code2");
    //         if ($err2) error_log("CURL ERROR: $err2");
    //         error_log("RESPONSE HEADERS: " . $h2);
    //         error_log("RESPONSE BODY: " . $b2);
    //         error_log("==============================");

    //         if ($code2 < 200 || $code2 >= 300) {
    //             throw new Exception("Reset password failed after multipart retry with HTTP $code2. See PHP error log for details.");
    //         }

    //         return json_decode($b2, true);
    //     }

    //     throw new Exception("Reset password failed with HTTP $httpCode. See PHP error log for details.");
    // }

    return json_decode($body, true);
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
