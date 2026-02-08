<?php
/**
 * Custom head includes for IBL5
 * Loaded by Nuke\Header::head() in the <head> section
 */

// Google Fonts - Barlow Condensed for display, Barlow for body
// Using display=block to prevent FOUT (flash of unstyled text)
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Barlow:wght@400;500;600;700&display=block" rel="stylesheet">';

// Font loading styles - hide content until fonts are ready
echo '<style id="font-loading-styles">
.fonts-loading {
    visibility: hidden;
}
.fonts-loaded {
    visibility: visible;
}
</style>';

// Font loading detection script (runs immediately, before body)
echo '<script>
// Add fonts-loading class immediately
document.documentElement.classList.add("fonts-loading");

// Check if fonts are already cached
if (document.fonts && document.fonts.check("1em Barlow")) {
    document.documentElement.classList.remove("fonts-loading");
    document.documentElement.classList.add("fonts-loaded");
} else if (document.fonts) {
    // Wait for fonts to load
    Promise.all([
        document.fonts.load("400 1em Barlow"),
        document.fonts.load("600 1em Barlow Condensed")
    ]).then(function() {
        document.documentElement.classList.remove("fonts-loading");
        document.documentElement.classList.add("fonts-loaded");
    }).catch(function() {
        // Fallback: show content anyway if fonts fail
        document.documentElement.classList.remove("fonts-loading");
        document.documentElement.classList.add("fonts-loaded");
    });

    // Safety timeout - show content after 1 second max
    setTimeout(function() {
        document.documentElement.classList.remove("fonts-loading");
        document.documentElement.classList.add("fonts-loaded");
    }, 1000);
} else {
    // Fallback for browsers without Font Loading API
    document.documentElement.classList.remove("fonts-loading");
    document.documentElement.classList.add("fonts-loaded");
}
</script>';

// Navigation JavaScript for mobile menu toggle
echo '<script src="' . ($relativePath ?? '') . 'jslib/navigation.js" defer></script>';

// Critical inline styles that must load immediately
// Note: Most styles are now in the compiled CSS (design/input.css -> themes/IBL/style/style.css)
// These remain inline because they must be available before any CSS files load
echo '<style>
/* FOUT Prevention - Hide body until fonts are loaded */
.fonts-loading body {
    visibility: hidden;
}
.fonts-loaded body {
    visibility: visible;
}
</style>';

// Load the compiled CSS from the design system
$cssFile = 'themes/IBL/style/style.css';
$cssVer = file_exists($cssFile) ? (string) filemtime($cssFile) : '';
echo '<link rel="stylesheet" href="' . ($relativePath ?? '') . $cssFile . '?v=' . $cssVer . '">';
