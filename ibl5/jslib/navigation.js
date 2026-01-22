/**
 * IBL5 Navigation - Mobile Menu Toggle
 * Minimal JavaScript for hamburger menu functionality
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var hamburger = document.getElementById('nav-hamburger');
        var mobileMenu = document.getElementById('nav-mobile-menu');
        var menuOverlay = document.getElementById('nav-overlay');

        if (!hamburger || !mobileMenu) {
            return;
        }

        // Toggle mobile menu
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = mobileMenu.classList.contains('translate-x-0');

            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        // Close when clicking overlay
        if (menuOverlay) {
            menuOverlay.addEventListener('click', closeMenu);
        }

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        function openMenu() {
            mobileMenu.classList.remove('-translate-x-full');
            mobileMenu.classList.add('translate-x-0');
            if (menuOverlay) {
                menuOverlay.classList.remove('hidden');
            }
            hamburger.setAttribute('aria-expanded', 'true');
        }

        function closeMenu() {
            mobileMenu.classList.add('-translate-x-full');
            mobileMenu.classList.remove('translate-x-0');
            if (menuOverlay) {
                menuOverlay.classList.add('hidden');
            }
            hamburger.setAttribute('aria-expanded', 'false');
        }

        // Handle mobile dropdown toggles
        var mobileDropdownBtns = document.querySelectorAll('.mobile-dropdown-btn');
        mobileDropdownBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var dropdown = this.nextElementSibling;
                var arrow = this.querySelector('.dropdown-arrow');

                if (dropdown.classList.contains('hidden')) {
                    dropdown.classList.remove('hidden');
                    if (arrow) arrow.classList.add('rotate-180');
                } else {
                    dropdown.classList.add('hidden');
                    if (arrow) arrow.classList.remove('rotate-180');
                }
            });
        });
    });
})();
