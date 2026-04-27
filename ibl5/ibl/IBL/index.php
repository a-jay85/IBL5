<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

$allowedFiles = ['Schedule.htm', 'Standings.htm'];

$file = $_GET['file'] ?? '';
if (!is_string($file) || !in_array($file, $allowedFiles, true)) {
    http_response_code(404);
    exit;
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (!is_string($ua) || $ua === '' || preg_match('/bot|crawler|spider|wget|curl|python|java|go-http|httpclient|slurp|bingpreview|mediapartners/i', $ua) === 1) {
    http_response_code(410);
    exit;
}

global $user;

if (!is_user($user)) {
    $_SESSION['redirect_after_login_path'] = 'ibl/IBL/' . $file;
    header('Location: /ibl5/modules.php?name=YourAccount');
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__ . '/' . $file);
