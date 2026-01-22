<?php
/**
 * Custom head includes for IBL5
 * Loaded by Nuke\Header::head() in the <head> section
 */

// Google Fonts - Bebas Neue for logo, Plus Jakarta Sans for body
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">';

// Tailwind CSS via CDN with custom config
echo '<script src="https://cdn.tailwindcss.com"></script>';
echo '<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                display: ["Bebas Neue", "sans-serif"],
                sans: ["Plus Jakarta Sans", "system-ui", "sans-serif"],
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

// Body padding and custom navigation styles
echo '<style>
body {
    padding-top: 64px !important;
    font-family: "Plus Jakarta Sans", system-ui, sans-serif;
}

/* Navigation dropdown items - staggered reveal with transitions */
.nav-dropdown-item {
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.2s ease-out, transform 0.2s ease-out;
}

.group:hover .nav-dropdown-item {
    opacity: 1;
    transform: translateY(0);
}

.group:hover .nav-dropdown-item:nth-child(1) { transition-delay: 0ms; }
.group:hover .nav-dropdown-item:nth-child(2) { transition-delay: 25ms; }
.group:hover .nav-dropdown-item:nth-child(3) { transition-delay: 50ms; }
.group:hover .nav-dropdown-item:nth-child(4) { transition-delay: 75ms; }
.group:hover .nav-dropdown-item:nth-child(5) { transition-delay: 100ms; }
.group:hover .nav-dropdown-item:nth-child(6) { transition-delay: 125ms; }
.group:hover .nav-dropdown-item:nth-child(7) { transition-delay: 150ms; }
.group:hover .nav-dropdown-item:nth-child(8) { transition-delay: 175ms; }
.group:hover .nav-dropdown-item:nth-child(9) { transition-delay: 200ms; }
.group:hover .nav-dropdown-item:nth-child(10) { transition-delay: 225ms; }
.group:hover .nav-dropdown-item:nth-child(11) { transition-delay: 250ms; }

/* Mobile menu custom scrollbar */
.mobile-menu-scroll::-webkit-scrollbar {
    width: 4px;
}
.mobile-menu-scroll::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}
.mobile-menu-scroll::-webkit-scrollbar-thumb {
    background: rgba(249,115,22,0.4);
    border-radius: 4px;
}
.mobile-menu-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(249,115,22,0.6);
}

/* Grain texture overlay */
.nav-grain::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'3\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E");
    opacity: 0.03;
    pointer-events: none;
    mix-blend-mode: overlay;
}

/* Mobile menu section animation */
.mobile-section {
    opacity: 0;
    transform: translateX(20px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.mobile-menu-open .mobile-section:nth-child(1) { transition-delay: 0.05s; }
.mobile-menu-open .mobile-section:nth-child(2) { transition-delay: 0.1s; }
.mobile-menu-open .mobile-section:nth-child(3) { transition-delay: 0.15s; }
.mobile-menu-open .mobile-section:nth-child(4) { transition-delay: 0.2s; }
.mobile-menu-open .mobile-section:nth-child(5) { transition-delay: 0.25s; }
.mobile-menu-open .mobile-section:nth-child(6) { transition-delay: 0.3s; }
.mobile-menu-open .mobile-section:nth-child(7) { transition-delay: 0.35s; }
.mobile-menu-open .mobile-section:nth-child(8) { transition-delay: 0.4s; }
.mobile-menu-open .mobile-section:nth-child(9) { transition-delay: 0.45s; }

.mobile-menu-open .mobile-section {
    opacity: 1;
    transform: translateX(0);
}
</style>';
