/**
 * IBL5 Navigation - Premium Mobile Menu
 * Handles hamburger animation and staggered menu reveals
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var hamburger = document.getElementById('nav-hamburger');
        var mobileMenu = document.getElementById('nav-mobile-menu');
        var menuOverlay = document.getElementById('nav-overlay');
        var hamburgerTop = document.getElementById('hamburger-top');
        var hamburgerMiddle = document.getElementById('hamburger-middle');
        var hamburgerBottom = document.getElementById('hamburger-bottom');

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
            // Slide in menu
            mobileMenu.classList.remove('translate-x-full');
            mobileMenu.classList.add('translate-x-0');

            // Add class for staggered animations
            setTimeout(function() {
                mobileMenu.classList.add('mobile-menu-open');
            }, 50);

            // Show overlay with fade
            if (menuOverlay) {
                menuOverlay.classList.remove('hidden');
                menuOverlay.style.opacity = '0';
                setTimeout(function() {
                    menuOverlay.style.opacity = '1';
                }, 10);
            }

            // Animate hamburger to X
            if (hamburgerTop && hamburgerMiddle && hamburgerBottom) {
                hamburgerTop.style.transform = 'translateY(7px) rotate(45deg)';
                hamburgerMiddle.style.opacity = '0';
                hamburgerBottom.style.transform = 'translateY(-7px) rotate(-45deg)';
            }

            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            // Remove stagger animation class first
            mobileMenu.classList.remove('mobile-menu-open');

            // Slide out menu
            mobileMenu.classList.add('translate-x-full');
            mobileMenu.classList.remove('translate-x-0');

            // Fade out and hide overlay
            if (menuOverlay) {
                menuOverlay.style.opacity = '0';
                setTimeout(function() {
                    menuOverlay.classList.add('hidden');
                }, 300);
            }

            // Animate hamburger back to lines
            if (hamburgerTop && hamburgerMiddle && hamburgerBottom) {
                hamburgerTop.style.transform = '';
                hamburgerMiddle.style.opacity = '1';
                hamburgerBottom.style.transform = '';
            }

            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        // Handle mobile dropdown toggles
        var mobileDropdownBtns = document.querySelectorAll('.mobile-dropdown-btn');
        mobileDropdownBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var dropdown = this.nextElementSibling;
                var arrow = this.querySelector('.dropdown-arrow');

                // Close other dropdowns
                mobileDropdownBtns.forEach(function(otherBtn) {
                    if (otherBtn !== btn) {
                        var otherDropdown = otherBtn.nextElementSibling;
                        var otherArrow = otherBtn.querySelector('.dropdown-arrow');
                        if (otherDropdown && !otherDropdown.classList.contains('hidden')) {
                            otherDropdown.classList.add('hidden');
                            if (otherArrow) otherArrow.classList.remove('rotate-180');
                        }
                    }
                });

                // Toggle current dropdown
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
