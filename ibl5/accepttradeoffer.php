<?php
// Redirect stub for moved file - handles both GET and POST requests

$newLocation = 'web/trade/accepttradeoffer.php';

// If this is a GET request or no POST data, use simple redirect
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $newLocation . $queryString);
    exit();
}

// For POST requests, forward the data using cURL
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
       '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $newLocation;

if (!empty($_SERVER['QUERY_STRING'])) {
    $url .= '?' . $_SERVER['QUERY_STRING'];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);

// Forward relevant headers
$headers = [];
if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
    $headers[] = 'Content-Type: ' . $_SERVER['HTTP_CONTENT_TYPE'];
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $headers[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Set the same HTTP response code
http_response_code($httpCode);

// Output the response
echo $response;
exit();
?>