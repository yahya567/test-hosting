<?php
// Extract CSRF token from MediaCMS login page HTML
function extractCsrf($html) {
    if (preg_match('/name="csrfmiddlewaretoken" value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

// Parse cookies from HTTP headers
function parseCookies($headers) {
    $cookies = [];
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            $cookie = trim(str_replace('Set-Cookie:', '', $header));
            $parts = explode(';', $cookie);
            $kv = explode('=', $parts[0], 2);
            $cookies[$kv[0]] = $kv[1];
        }
    }
    return $cookies;
}
