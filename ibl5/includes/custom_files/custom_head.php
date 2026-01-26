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

// Tailwind CSS via CDN with custom config
echo '<script src="https://cdn.tailwindcss.com"></script>';
echo '<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                display: ["Barlow Condensed", "sans-serif"],
                sans: ["Barlow", "system-ui", "sans-serif"],
            },
            colors: {
                navy: {
                    900: "#0a0f1a",
                    800: "#111827",
                    700: "#1a2234",
                    600: "#243044",
                },
                accent: {
                    500: "#f97316",
                    400: "#fb923c",
                    300: "#fdba74",
                },
            },
            animation: {
                "fade-in": "fadeIn 0.2s ease-out forwards",
                "slide-up": "slideUp 0.2s ease-out forwards",
                "slide-in-right": "slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards",
            },
            keyframes: {
                fadeIn: {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                slideUp: {
                    "0%": { opacity: "0", transform: "translateY(8px)" },
                    "100%": { opacity: "1", transform: "translateY(0)" },
                },
                slideInRight: {
                    "0%": { transform: "translateX(100%)" },
                    "100%": { transform: "translateX(0)" },
                },
            },
        },
    },
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
echo '<link rel="stylesheet" href="' . ($relativePath ?? '') . 'themes/IBL/style/style.css">';
