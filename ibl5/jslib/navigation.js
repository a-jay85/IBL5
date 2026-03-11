/**
 * IBL5 Navigation - Premium Mobile Menu
 * Handles hamburger animation and staggered menu reveals
 *
 * Runs immediately when DOM is ready, or defers if still loading.
 * Does not rely on DOMContentLoaded alone, so it works reliably
 * even when loaded dynamically or with cached scripts.
 */
(function() {
    'use strict';

    function initNavigation() {
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
            // Use rem units so translation scales with container size (h-4 = 1rem)
            // 0.4375rem = 7px at 16px root font, scales proportionally
            if (hamburgerTop && hamburgerMiddle && hamburgerBottom) {
                hamburgerTop.style.transform = 'translateY(0.4375rem) rotate(45deg)';
                hamburgerMiddle.style.opacity = '0';
                hamburgerBottom.style.transform = 'translateY(-0.4375rem) rotate(-45deg)';
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

        // Desktop dropdown click-to-pin
        // Clicking a column heading keeps it open; clicking again closes it.
        // Hover behavior continues to work independently.
        var desktopGroups = document.querySelectorAll('nav.nav-grain .group');

        desktopGroups.forEach(function(group) {
            var btn = group.querySelector('button');
            if (!btn) return;

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var wasPinned = group.classList.contains('nav-pinned');

                // Close all other pinned dropdowns
                desktopGroups.forEach(function(other) {
                    if (other !== group) {
                        other.classList.remove('nav-pinned');
                    }
                });

                // Toggle current dropdown
                if (wasPinned) {
                    group.classList.remove('nav-pinned');
                } else {
                    group.classList.add('nav-pinned');
                }
            });
        });

        // Close pinned dropdowns when clicking outside nav groups
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.group')) {
                desktopGroups.forEach(function(group) {
                    group.classList.remove('nav-pinned');
                });
            }
        });

        // Close pinned dropdowns on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                desktopGroups.forEach(function(group) {
                    group.classList.remove('nav-pinned');
                });
            }
        });

        // Close mobile menu when a boosted link is tapped
        // so the user can see the new content loading behind the menu
        mobileMenu.addEventListener('click', function(e) {
            var link = e.target.closest('a[hx-boost="true"]');
            if (link) {
                closeMenu();
            }
        });

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
    }

    // Desktop/Mobile view toggle — runs immediately (no DOM dependency
    // beyond the toggle buttons which are parsed before this script).
    function initViewToggle() {
        var desktopToggle = document.getElementById('desktop-view-toggle');
        var mobileToggle = document.getElementById('mobile-view-toggle');

        // Show mobile-view toggle when desktop-view is forced
        if (mobileToggle && document.documentElement.classList.contains('desktop-view-active')) {
            mobileToggle.classList.remove('hidden');
        }

        function enableDesktopView() {
            try { localStorage.setItem('ibl_desktop_view', '1'); } catch (e) {}
            window.location.reload();
        }

        function enableMobileView() {
            try { localStorage.removeItem('ibl_desktop_view'); } catch (e) {}
            window.location.reload();
        }

        if (desktopToggle) {
            desktopToggle.addEventListener('click', enableDesktopView);
        }
        if (mobileToggle) {
            mobileToggle.addEventListener('click', enableMobileView);
        }
    }

    // Run immediately if DOM is ready, otherwise wait.
    // defer scripts run when readyState is 'interactive' (after parsing),
    // so the 'else' branch is typically taken. The 'loading' check is a
    // safety net for edge cases (e.g., slow script load, browser quirks).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initNavigation();
            initViewToggle();
        });
    } else {
        initNavigation();
        initViewToggle();
    }
})();
