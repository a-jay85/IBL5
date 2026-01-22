<?php
/**
 * Custom head includes for IBL5
 * Loaded by Nuke\Header::head() in the <head> section
 */

// Tailwind CSS via CDN
echo '<script src="https://cdn.tailwindcss.com"></script>';

// Navigation JavaScript for mobile menu toggle
echo '<script src="' . ($relativePath ?? '') . 'jslib/navigation.js" defer></script>';

// Body padding to account for fixed navigation bar
echo '<style>body { padding-top: 56px !important; }</style>';
