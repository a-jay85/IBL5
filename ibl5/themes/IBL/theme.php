<?php

/**
 * IBL Court Side Theme
 * Mobile-first, athletic, data-driven design
 */

$lnkcolor = "#1E3A5F";
if ($_SERVER['SERVER_NAME'] != "localhost") {
    $bgcolor1 = "#FAFAF9";
} else {
    $bgcolor1 = "#F5F5F4";
}
$bgcolor2 = "#E7E5E4";
$bgcolor3 = "#D6D3D1";
$textcolor1 = "#44403C";
$textcolor2 = "#292524";
$theme_home = "Web_Links";
$hr = 1;

function OpenTable()
{
    echo '<div class="ibl-card">' . "\n";
    echo '<div class="ibl-card-body">' . "\n";
}

function OpenTable2()
{
    echo '<div class="ibl-card max-w-4xl mx-auto">' . "\n";
    echo '<div class="ibl-card-body">' . "\n";
}

function CloseTable()
{
    echo '</div>' . "\n"; // close ibl-card-body
    echo '</div>' . "\n"; // close ibl-card
}

function CloseTable2()
{
    echo '</div>' . "\n";
    echo '</div>' . "\n";
}

function FormatStory($thetext, $notes, $aid, $informant)
{
    global $anonymous;
    if (!empty($notes)) {
        $notes = "<strong>" . _NOTE . "</strong> <em>$notes</em>\n";
    } else {
        $notes = "";
    }
    if ("$aid" == "$informant") {
        echo "<p class=\"text-sm leading-relaxed\">$thetext</p>\n";
        if ($notes) {
            echo "<p class=\"text-sm text-surface-500 mt-2\">$notes</p>\n";
        }
    } else {
        if (!empty($informant)) {
            $boxstuff = "<a href=\"modules.php?name=Your_Account&amp;op=userinfo&amp;username=$informant\">$informant</a> ";
        } else {
            $boxstuff = "$anonymous ";
        }
        $boxstuff .= "" . _WRITES . " <em>\"$thetext\"</em> $notes\n";
        echo "<p class=\"text-sm leading-relaxed\">$boxstuff</p>\n";
    }
}

function themeheader()
{
    global $user, $cookie, $bgcolor1, $leagueContext;

    // Prepare user state early
    if (is_user($user)) {
        cookiedecode($user);
        $username = $cookie[1];
        $userGreeting = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $userLoggedIn = true;
    } else {
        $userGreeting = "";
        $userLoggedIn = false;
    }

    // League switcher data
    $currentLeague = $leagueContext->getCurrentLeague();

    // Output proper HTML document structure
    ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1E3A5F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>IBL - Internet Basketball League</title>

    <!-- Preconnect to Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="themes/IBL/dist/app.css">

    <!-- Alpine.js from CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Inline critical styles for body scroll lock -->
    <style>
        body.drawer-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body class="ibl-layout bg-surface-50 antialiased" x-data="{ drawerOpen: false }" :class="{ 'drawer-open': drawerOpen }">

    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-ibl-primary text-white px-4 py-2 rounded-button z-50">
        Skip to main content
    </a>

    <header class="ibl-header">
        <div class="ibl-header-inner">
            <!-- Logo -->
            <a href="index.php" class="ibl-logo group">
                <svg class="w-8 h-8 text-ibl-primary group-hover:text-ibl-accent transition-colors" viewBox="0 0 32 32" fill="currentColor">
                    <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="2" fill="none"/>
                    <path d="M16 4 L16 28 M4 16 L28 16" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="16" cy="16" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                </svg>
                <span class="font-bold">IBL</span>
            </a>

            <!-- Desktop Navigation -->
            <nav class="ibl-desktop-nav">
                <a href="index.php" class="ibl-desktop-nav-link">Home</a>
                <a href="modules.php?name=Standings" class="ibl-desktop-nav-link">Standings</a>
                <a href="modules.php?name=Leaderboards" class="ibl-desktop-nav-link">Leaders</a>
                <a href="modules.php?name=Schedule" class="ibl-desktop-nav-link">Schedule</a>
                <a href="modules.php?name=Teams" class="ibl-desktop-nav-link">Teams</a>

                <!-- League Switcher -->
                <select
                    onchange="window.location.href=this.value"
                    class="ibl-select ml-2 py-1.5 text-sm w-auto min-w-[100px]"
                    aria-label="Switch league"
                >
                    <option value="index.php?league=ibl"<?= $currentLeague === 'ibl' ? ' selected' : '' ?>>IBL</option>
                    <option value="index.php?league=olympics"<?= $currentLeague === 'olympics' ? ' selected' : '' ?>>Olympics</option>
                </select>
            </nav>

            <!-- User Section (Desktop) -->
            <div class="hidden lg:flex items-center gap-3">
                <?php if ($userLoggedIn): ?>
                    <span class="text-sm text-surface-600">Hello, <strong><?= $userGreeting ?></strong></span>
                    <a href="modules.php?name=Your_Account&amp;op=logout" class="ibl-btn ibl-btn-ghost ibl-btn-sm">Logout</a>
                <?php else: ?>
                    <a href="modules.php?name=Your_Account" class="ibl-btn ibl-btn-ghost ibl-btn-sm">Login</a>
                    <a href="modules.php?name=Your_Account&amp;op=new_user" class="ibl-btn ibl-btn-primary ibl-btn-sm">Sign Up</a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <button
                @click="drawerOpen = true"
                class="ibl-menu-btn"
                aria-label="Open menu"
                :aria-expanded="drawerOpen.toString()"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </header>

    <!-- Mobile Drawer Overlay -->
    <div
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="drawerOpen = false"
        class="ibl-drawer-overlay"
        aria-hidden="true"
    ></div>

    <!-- Mobile Drawer -->
    <aside
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @keydown.escape.window="drawerOpen = false"
        class="ibl-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Navigation menu"
    >
        <!-- Drawer Header -->
        <div class="ibl-drawer-header">
            <span class="ibl-drawer-title">Menu</span>
            <button @click="drawerOpen = false" class="ibl-drawer-close" aria-label="Close menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Drawer Body -->
        <div class="ibl-drawer-body" id="mobile-menu-container">
            <!-- User Section (Mobile) -->
            <?php if ($userLoggedIn): ?>
                <div class="px-3 py-4 mb-4 bg-gradient-to-r from-ibl-primary/10 to-ibl-accent/5 rounded-card">
                    <div class="text-xs uppercase tracking-wide text-surface-500 mb-1">Signed in as</div>
                    <div class="font-display font-semibold text-surface-800"><?= $userGreeting ?></div>
                </div>
            <?php endif; ?>

            <!-- League Switcher (Mobile) -->
            <div class="px-3 mb-6">
                <label class="ibl-label">League</label>
                <select
                    onchange="window.location.href=this.value"
                    class="ibl-select w-full"
                >
                    <option value="index.php?league=ibl"<?= $currentLeague === 'ibl' ? ' selected' : '' ?>>IBL</option>
                    <option value="index.php?league=olympics"<?= $currentLeague === 'olympics' ? ' selected' : '' ?>>Olympics</option>
                </select>
            </div>

            <!-- Navigation Groups -->
            <nav class="space-y-2">
                <div class="ibl-nav-group">
                    <div class="ibl-nav-group-title">Main</div>
                    <a href="index.php" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span>Home</span>
                    </a>
                    <a href="modules.php?name=Standings" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <span>Standings</span>
                    </a>
                    <a href="modules.php?name=Leaderboards" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        <span>Leaderboards</span>
                    </a>
                    <a href="modules.php?name=Schedule" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Schedule</span>
                    </a>
                </div>

                <div class="ibl-nav-group">
                    <div class="ibl-nav-group-title">Teams & Players</div>
                    <a href="modules.php?name=Teams" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span>Teams</span>
                    </a>
                    <a href="modules.php?name=PlayerSearch" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span>Player Search</span>
                    </a>
                    <a href="modules.php?name=FreeAgency" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        <span>Free Agency</span>
                    </a>
                </div>

                <div class="ibl-nav-group">
                    <div class="ibl-nav-group-title">League Info</div>
                    <a href="modules.php?name=News" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                        <span>News</span>
                    </a>
                    <a href="modules.php?name=Topics" class="ibl-nav-link" @click="drawerOpen = false">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                        <span>Message Board</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Drawer Footer -->
        <div class="ibl-drawer-footer">
            <?php if ($userLoggedIn): ?>
                <div class="flex gap-2">
                    <a href="modules.php?name=Your_Account" class="ibl-btn ibl-btn-secondary flex-1 justify-center">My Account</a>
                    <a href="modules.php?name=Your_Account&amp;op=logout" class="ibl-btn ibl-btn-ghost">Logout</a>
                </div>
            <?php else: ?>
                <div class="flex gap-2">
                    <a href="modules.php?name=Your_Account" class="ibl-btn ibl-btn-secondary flex-1 justify-center">Login</a>
                    <a href="modules.php?name=Your_Account&amp;op=new_user" class="ibl-btn ibl-btn-primary flex-1 justify-center">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <?php
    // Public message banner
    $public_msg = public_message();
    if (!empty($public_msg)) {
        echo '<div class="bg-gradient-to-r from-ibl-accent/10 to-ibl-primary/5 border-b border-ibl-accent/20">';
        echo '<div class="container-ibl py-3 text-sm text-center text-surface-700">' . $public_msg . '</div>';
        echo '</div>';
    }

    // ========================================
    // MAIN CONTENT LAYOUT
    // ========================================
    echo '<div class="container-ibl py-4 md:py-6">';

    // Main content area
    echo '<main id="main-content" class="space-y-4">';
}

function themefooter()
{
    // Close main content area
    echo '</main>';

    echo '</div>'; // Close container

    // ========================================
    // FOOTER
    // ========================================
    ?>
    <footer class="ibl-footer">
        <div class="ibl-footer-inner">
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <svg class="w-6 h-6 text-surface-400" viewBox="0 0 32 32" fill="currentColor">
                        <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M16 4 L16 28 M4 16 L28 16" stroke="currentColor" stroke-width="1.5"/>
                        <circle cx="16" cy="16" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                    <span class="font-display font-semibold text-white">IBL</span>
                </div>
            </div>
            <div class="mb-4 text-surface-400">
                <?php Nuke\Footer::footmsg(); ?>
            </div>
            <div class="text-surface-500 text-xs">
                &copy; <?= date('Y') ?> Internet Basketball League. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Additional scripts for dynamic sidebar links in mobile menu -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const mobileMenu = document.getElementById('mobile-menu-container');
            const asides = document.querySelectorAll('aside.hidden');

            if (mobileMenu && asides.length > 0) {
                const existingLinks = new Set();
                mobileMenu.querySelectorAll('a').forEach(a => {
                    existingLinks.add(a.getAttribute('href'));
                });

                const additionalLinks = [];
                asides.forEach(aside => {
                    aside.querySelectorAll('a[href]').forEach(a => {
                        const href = a.getAttribute('href');
                        const text = a.textContent.trim();
                        if (href &&
                            !href.startsWith('javascript:') &&
                            !href.startsWith('#') &&
                            !existingLinks.has(href) &&
                            text.length > 0 &&
                            text.length < 50) {
                            existingLinks.add(href);
                            additionalLinks.push({ href, text });
                        }
                    });
                });

                if (additionalLinks.length > 0) {
                    const nav = mobileMenu.querySelector('nav');
                    if (nav) {
                        const moreGroup = document.createElement('div');
                        moreGroup.className = 'ibl-nav-group';
                        moreGroup.innerHTML = '<div class="ibl-nav-group-title">More</div>';

                        additionalLinks.slice(0, 8).forEach(link => {
                            const a = document.createElement('a');
                            a.href = link.href;
                            a.className = 'ibl-nav-link';
                            a.innerHTML = '<svg class="w-5 h-5 flex-shrink-0 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span>' + link.text + '</span>';
                            moreGroup.appendChild(a);
                        });

                        nav.appendChild(moreGroup);
                    }
                }
            }
        } catch (e) {
            console.error('Mobile menu error:', e);
        }
    });
    </script>
</body>
</html>
    <?php
}

function themeindex($aid, $informant, $time, $title, $counter, $topic, $thetext, $notes, $morelink, $topicname, $topicimage, $topictext)
{
    global $tipath;
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/images/topics/$topicimage")) {
        $t_image = "themes/$ThemeSel/images/topics/$topicimage";
    } else {
        $t_image = "$tipath$topicimage";
    }
    ?>
    <article class="ibl-card group hover:shadow-card-hover transition-shadow duration-200">
        <div class="ibl-card-header">
            <h2 class="ibl-card-title group-hover:text-ibl-primary transition-colors"><?= $title ?></h2>
        </div>
        <div class="ibl-card-body">
            <div class="flex items-start gap-4">
                <?php if ($t_image): ?>
                <a href="modules.php?name=News&amp;new_topic=<?= $topic ?>" class="flex-shrink-0 hidden xs:block">
                    <img src="<?= $t_image ?>" alt="<?= htmlspecialchars($topictext, ENT_QUOTES, 'UTF-8') ?>" class="w-14 h-14 sm:w-16 sm:h-16 object-contain rounded">
                </a>
                <?php endif; ?>
                <div class="flex-1 min-w-0">
                    <?php FormatStory($thetext, $notes, $aid, $informant); ?>
                </div>
            </div>
        </div>
        <div class="ibl-card-footer flex flex-col xs:flex-row items-start xs:items-center justify-between gap-2">
            <div class="text-xs text-surface-500">
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= $time ?>
                </span>
                <span class="mx-1">&middot;</span>
                <strong><?php formatAidHeader($aid); ?></strong>
                <span class="hidden sm:inline ml-1">&middot; <?= $counter ?> <?= _READS ?></span>
            </div>
            <div class="text-sm font-medium"><?= $morelink ?></div>
        </div>
    </article>
    <?php
}

function themearticle($aid, $informant, $datetime, $title, $thetext, $topic, $topicname, $topicimage, $topictext)
{
    global $tipath;
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/images/topics/$topicimage")) {
        $t_image = "themes/$ThemeSel/images/topics/$topicimage";
    } else {
        $t_image = "$tipath$topicimage";
    }
    ?>
    <article class="ibl-card">
        <div class="ibl-card-header">
            <h1 class="ibl-card-title text-xl sm:text-2xl"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="ibl-card-body">
            <div class="flex flex-col sm:flex-row items-start gap-4 mb-6 pb-4 border-b border-surface-200">
                <?php if ($t_image): ?>
                <a href="modules.php?name=News&amp;new_topic=<?= $topic ?>" class="flex-shrink-0">
                    <img src="<?= $t_image ?>" alt="<?= htmlspecialchars($topictext, ENT_QUOTES, 'UTF-8') ?>" class="w-20 h-20 object-contain rounded">
                </a>
                <?php endif; ?>
                <div class="text-sm text-surface-500 space-y-1">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <?= _POSTEDON ?> <?= $datetime ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        <?= _TOPIC ?>: <a href="modules.php?name=News&amp;new_topic=<?= $topic ?>" class="hover:underline"><?= htmlspecialchars($topictext, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </div>
            <div class="prose prose-sm sm:prose max-w-none text-surface-700 leading-relaxed">
                <?= $thetext ?>
            </div>
        </div>
    </article>
    <?php
}

function themesidebox($title, $content)
{
    echo View\ThemeComponents::sidebarBox($title, $content);
}
