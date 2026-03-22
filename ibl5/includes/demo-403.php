<?php

declare(strict_types=1);

/**
 * Friendly 403 page shown when the demo user attempts a POST request.
 * Explains that form submissions are intentionally disabled.
 *
 * Note: The page content must exceed ~1.5KB to prevent Chrome from
 * replacing it with its own "Access Denied" error page.
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Submission Disabled - IBL</title>
    <link rel="stylesheet" href="/ibl5/themes/IBL/style/style.css">
</head>
<body class="bg-page min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full bg-white rounded-lg shadow-md p-8 text-center">
        <h1 class="text-2xl font-bold text-navy-900 mb-4">Form Submission Disabled</h1>
        <p class="text-gray-700 mb-6">
            This is a read-only demo account. Form submissions are intentionally
            disabled to protect the integrity of the site's data.
        </p>
        <p class="text-gray-500 text-sm mb-6">
            You are browsing the IBL site as a guest viewer. All pages and data
            are fully visible, but actions that modify data (submitting trades,
            depth charts, free agent offers, votes, and other forms) have been
            disabled for this session. This restriction ensures the live league
            data remains accurate for active participants.
        </p>
        <a href="javascript:history.back()"
           class="inline-block px-6 py-2 bg-navy-800 text-white rounded hover:bg-navy-700 transition-colors">
            Go Back
        </a>
    </div>
</body>
</html>
