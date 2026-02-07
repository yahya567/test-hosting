<?php
require 'config.php';
require 'helpers.php';

$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

if (!$phone || !$password) {
    die("Missing credentials");
}

// ---------------- STEP 1: GET CSRF ----------------
$ch = curl_init(MEDIACMS_BASE . '/accounts/login/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => ENV === 'prod',
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
$headersRaw = substr($response, 0, $headerSize);

// Extract CSRF
$csrf = extractCsrf($body);

// Extract cookies
preg_match_all('/set-cookie:\s*([^=]+)=([^;]+)/i', strtolower($headersRaw), $matches);
$cookies = [];
if (!empty($matches[1])) {
    foreach ($matches[1] as $i => $name) {
        $cookies[trim($name)] = trim($matches[2][$i]);
    }
}

$csrfCookie = $cookies['csrftoken'] ?? '';
error_log("CSRF: $csrf, CSRF_COOKIE: $csrfCookie");

// ---------------- STEP 2: POST LOGIN ----------------
$postFields = http_build_query([
    'login' => $phone,
    'password' => $password,
    'csrfmiddlewaretoken' => $csrf,
]);

$ch = curl_init(MEDIACMS_BASE . '/accounts/login/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => ENV === 'prod',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => [
        "Cookie: csrftoken=$csrfCookie"
    ],
]);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headersRaw = substr($response, 0, $headerSize);

// parse cookies after login
preg_match_all('/set-cookie:\s*([^=]+)=([^;]+)/i', strtolower($headersRaw), $matches);
$sessionid = '';
foreach ($matches[1] as $i => $name) {
    if (trim($name) === 'sessionid') {
        $sessionid = trim($matches[2][$i]);
    }
}

if (!$sessionid) {
    die("Login failed. Check credentials or CSRF settings.");
}

// ---------------- STEP 3: Set cookie and redirect ----------------
setcookie('sessionid', $sessionid, [
    'expires' => time() + 3600,
    'path' => '/app/', // restrict to MediaCMS path
    'domain' => '',
    'secure' => ENV === 'prod',
    'httponly' => true,
    'samesite' => 'Lax'
]);

header("Location: /app/");
exit;