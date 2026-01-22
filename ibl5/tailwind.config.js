/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./themes/**/*.php",
    "./themes/**/*.blade.php",
    "./blocks/**/*.php",
    "./modules/**/*.php",
    "./classes/**/*.php"
  ],
  theme: {
    // Mobile-first breakpoints
    screens: {
      'xs': '375px',   // Small phones
      'sm': '480px',   // Large phones
      'md': '768px',   // Tablets
      'lg': '1024px',  // Laptops
      'xl': '1280px',  // Desktops
      '2xl': '1536px', // Large screens
    },
    extend: {
      colors: {
        // IBL Court Side palette
        'court': {
          'wood': '#D4A574',      // Warm court wood tone
          'wood-dark': '#B8956A', // Darker wood accent
          'line': '#FFFFFF',      // Court lines
        },
        'ibl': {
          'primary': '#1E3A5F',     // Deep navy - primary brand
          'primary-light': '#2D5A8A', // Lighter navy for hover
          'accent': '#FF6B35',      // Energetic orange accent
          'accent-muted': '#E85A2A', // Muted accent
          'success': '#10B981',     // Green for wins/positive
          'danger': '#EF4444',      // Red for losses/alerts
          'warning': '#F59E0B',     // Amber for cautions
        },
        // Refined grays with warm undertones
        'surface': {
          '50': '#FAFAF9',   // Lightest background
          '100': '#F5F5F4',  // Card backgrounds
          '200': '#E7E5E4',  // Borders, dividers
          '300': '#D6D3D1',  // Disabled states
          '400': '#A8A29E',  // Placeholder text
          '500': '#78716C',  // Secondary text
          '600': '#57534E',  // Primary text muted
          '700': '#44403C',  // Primary text
          '800': '#292524',  // Headings
          '900': '#1C1917',  // Darkest text
        },
        // Legacy support
        'ibl-gray-light': '#F5F5F4',
        'ibl-gray-medium': '#E7E5E4',
        'ibl-gray-dark': '#A8A29E',
        'ibl-link': '#1E3A5F',
      },
      fontFamily: {
        // Athletic, modern typography
        'display': ['"DM Sans"', 'system-ui', 'sans-serif'],
        'body': ['"Inter"', 'system-ui', 'sans-serif'],
        'mono': ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
        // Legacy
        'sans': ['"DM Sans"', 'Helvetica', 'Arial', 'sans-serif'],
      },
      fontSize: {
        // Mobile-optimized type scale
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.8125rem', { lineHeight: '1.25rem' }],
        'base': ['0.9375rem', { lineHeight: '1.5rem' }],
        'lg': ['1.0625rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        // Stats-specific sizes
        'stat-value': ['1.75rem', { lineHeight: '1', fontWeight: '700' }],
        'stat-label': ['0.6875rem', { lineHeight: '1', fontWeight: '500', letterSpacing: '0.05em' }],
      },
      spacing: {
        // Touch-friendly spacing
        'touch': '44px',  // Minimum touch target
        'safe-bottom': 'env(safe-area-inset-bottom)',
        'safe-top': 'env(safe-area-inset-top)',
      },
      borderRadius: {
        'card': '0.75rem',
        'button': '0.5rem',
        'pill': '9999px',
      },
      boxShadow: {
        'card': '0 1px 3px 0 rgb(0 0 0 / 0.05), 0 1px 2px -1px rgb(0 0 0 / 0.05)',
        'card-hover': '0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.07)',
        'elevated': '0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.08)',
        'drawer': '-4px 0 15px -3px rgb(0 0 0 / 0.1)',
        'top-bar': '0 1px 3px 0 rgb(0 0 0 / 0.05)',
      },
      animation: {
        'slide-in-right': 'slideInRight 0.3s cubic-bezier(0.32, 0.72, 0, 1)',
        'slide-out-right': 'slideOutRight 0.25s cubic-bezier(0.32, 0.72, 0, 1)',
        'fade-in': 'fadeIn 0.2s ease-out',
        'fade-out': 'fadeOut 0.15s ease-in',
        'scale-in': 'scaleIn 0.2s cubic-bezier(0.32, 0.72, 0, 1)',
        'pulse-subtle': 'pulseSubtle 2s ease-in-out infinite',
      },
      keyframes: {
        slideInRight: {
          '0%': { transform: 'translateX(100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        slideOutRight: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(100%)' },
        },
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeOut: {
          '0%': { opacity: '1' },
          '100%': { opacity: '0' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.95)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        pulseSubtle: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.7' },
        },
      },
      transitionTimingFunction: {
        'bounce-out': 'cubic-bezier(0.32, 0.72, 0, 1)',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
