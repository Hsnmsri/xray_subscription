<?php
// import includes
require_once("../config.php");

// get request uri
$requestUri = $_SERVER['REQUEST_URI'];

// base uri path
$expectedBasePath = '/' . $config['uri_path'] . '/';

// check uri path
if (strpos($requestUri, $expectedBasePath) !== 0) {
    http_response_code(404);
    die();
}

$param = substr($requestUri, strlen($expectedBasePath));

// encrypt string body
function encryptString($string, $key)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($string, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

if (!empty($param)) {
    $apiUrl = ($config['xray_panel_allow_ssl'] ? 'https' : 'http') . '://' . $config['xray_panel_domain'] . ':' . $config['xray_panel_port'] . '/' . $config['xray_uri_path'] . '/' . $param;

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Include the headers in the output

    // Execute the request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        http_response_code(500);
        echo 'Curl error: ' . curl_error($ch);
        curl_close($ch);
        exit;
    }

    // Separate headers and body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    // Get HTTP response code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close the cURL session
    curl_close($ch);

    // Set response header for the body content
    header('Content-Type: text/plain; charset=utf-8');

    // Set HTTP response code
    http_response_code($httpCode);

    // Send all headers to the client
    foreach (explode("\r\n", $header) as $line) {
        header($line);
    }

    // Return the response body
    echo encryptString($body, $config['encrypt_string_key']);
    exit;
}
