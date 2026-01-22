<?php
/**
 * Demo Page - New Theme Components
 *
 * Showcases the new Tailwind CSS styling, Blade templates, mobile menu,
 * and ThemeComponents without affecting the existing site.
 *
 * Access: http://localhost/demo.php
 */

declare(strict_types=1);

// Load autoloader directly (bypass mainfile.php to avoid redirects)
require_once __DIR__ . '/autoloader.php';

use View\BladeRenderer;
use View\ThemeComponents;
use Navigation\MobileMenuBuilder;
use Navigation\BlockLinkParser;

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize components
$bladeRenderer = new BladeRenderer(__DIR__ . '/themes/IBL');
$parser = new BlockLinkParser();
$menuBuilder = new MobileMenuBuilder($parser, __DIR__ . '/blocks');

// Build mobile menu data (with error handling)
try {
    $mobileMenu = $menuBuilder->build();
} catch (Exception $e) {
    // If blocks aren't accessible, use empty menu
    $mobileMenu = [
        'team' => [],
        'stats' => [],
        'site' => [],
        'account' => [],
        'other' => []
    ];
}

// Sample user data for header
$currentUser = null; // Set to null for logged-out view, or create a user object

// Render header
$headerData = [
    'user' => $currentUser,
    'currentLeague' => 'ibl',
    'mobileMenu' => $mobileMenu,
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBL5 - New Theme Demo</title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="themes/IBL/dist/app.css">

    <!-- Alpine.js -->
    <script defer src="node_modules/alpinejs/dist/cdn.min.js"></script>

    <style>
        /* Additional demo-specific styling */
        .demo-section {
            margin-bottom: 2rem;
        }
        .demo-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #336699;
            border-bottom: 2px solid #CCCCCC;
            padding-bottom: 0.5rem;
        }
        .code-preview {
            background: #f5f5f5;
            border-left: 4px solid #336699;
            padding: 1rem;
            margin-top: 0.5rem;
            font-family: monospace;
            font-size: 0.875rem;
            overflow-x: auto;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Render Header with Mobile Menu -->
<?php
try {
    echo $bladeRenderer->render('partials/header', $headerData);
} catch (Exception $e) {
    echo "<div style='background: #fee; border: 2px solid #f00; padding: 1rem; margin: 1rem;'>";
    echo "<strong>Header Render Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<!-- Main Content Area -->
<div class="container mx-auto px-4 py-8">

    <!-- Page Title -->
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-ibl-link mb-2">IBL5 New Theme Demo</h1>
        <p class="text-gray-600">Preview of Tailwind CSS components, mobile menu, and modern styling</p>
    </div>

    <!-- Demo Sections -->

    <!-- 1. Cards -->
    <div class="demo-section">
        <h2 class="demo-title">1. Card Components</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            echo ThemeComponents::openCard('Team Stats',
                '<p class="mb-4"><strong>Los Angeles Lakers</strong></p>
                <ul class="space-y-2 text-sm">
                    <li>Record: 45-37 (3rd in Western Conference)</li>
                    <li>PPG: 112.5 (8th in league)</li>
                    <li>RPG: 44.2 (12th in league)</li>
                    <li>APG: 26.8 (5th in league)</li>
                </ul>'
            );

            echo ThemeComponents::openCard('Recent Transactions',
                '<ul class="space-y-2 text-sm">
                    <li>✅ <strong>Trade:</strong> Acquired G Smith from Boston</li>
                    <li>📝 <strong>Extension:</strong> Signed C Johnson to 3-year deal</li>
                    <li>🔄 <strong>Waiver:</strong> Claimed F Davis off waivers</li>
                </ul>'
            );

            echo ThemeComponents::openCard('Upcoming Games',
                '<ul class="space-y-2 text-sm">
                    <li>01/21: vs. Boston Celtics (7:30 PM ET)</li>
                    <li>01/23: @ Miami Heat (8:00 PM ET)</li>
                    <li>01/25: vs. Phoenix Suns (7:30 PM ET)</li>
                </ul>'
            );
            ?>
        </div>

        <div class="code-preview">
            <strong>Usage:</strong><br>
            echo ThemeComponents::openCard('Card Title', '&lt;p&gt;Card content here&lt;/p&gt;');
        </div>
    </div>

    <!-- 2. Tables -->
    <div class="demo-section">
        <h2 class="demo-title">2. Table Components</h2>

        <?php
        $tableContent = '
            <thead>
                <tr>
                    <th class="px-4 py-2 text-left">Player</th>
                    <th class="px-4 py-2 text-center">Position</th>
                    <th class="px-4 py-2 text-right">PPG</th>
                    <th class="px-4 py-2 text-right">RPG</th>
                    <th class="px-4 py-2 text-right">APG</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="px-4 py-2">LeBron James</td>
                    <td class="px-4 py-2 text-center">F</td>
                    <td class="px-4 py-2 text-right">27.2</td>
                    <td class="px-4 py-2 text-right">7.5</td>
                    <td class="px-4 py-2 text-right">7.3</td>
                </tr>
                <tr>
                    <td class="px-4 py-2">Anthony Davis</td>
                    <td class="px-4 py-2 text-center">C</td>
                    <td class="px-4 py-2 text-right">24.0</td>
                    <td class="px-4 py-2 text-right">9.9</td>
                    <td class="px-4 py-2 text-right">3.1</td>
                </tr>
                <tr>
                    <td class="px-4 py-2">D\'Angelo Russell</td>
                    <td class="px-4 py-2 text-center">G</td>
                    <td class="px-4 py-2 text-right">17.8</td>
                    <td class="px-4 py-2 text-right">2.8</td>
                    <td class="px-4 py-2 text-right">6.2</td>
                </tr>
            </tbody>
        ';

        echo ThemeComponents::openTable($tableContent);
        ?>

        <div class="code-preview">
            <strong>Usage:</strong><br>
            echo ThemeComponents::openTable('&lt;thead&gt;...&lt;/thead&gt;&lt;tbody&gt;...&lt;/tbody&gt;');
        </div>
    </div>

    <!-- 3. Alerts -->
    <div class="demo-section">
        <h2 class="demo-title">3. Alert Components</h2>

        <?php
        echo ThemeComponents::alert('success', 'Trade successful! Your offer has been accepted.');
        echo ThemeComponents::alert('info', 'The next simulation will run on January 21, 2026 at 10:00 PM ET.');
        echo ThemeComponents::alert('warning', 'You are $2.5M over the salary cap. Please make roster adjustments.');
        echo ThemeComponents::alert('error', 'Invalid depth chart submission: You must have at least 5 players at each position.');
        ?>

        <div class="code-preview">
            <strong>Usage:</strong><br>
            echo ThemeComponents::alert('success|info|warning|error', 'Message text');
        </div>
    </div>

    <!-- 4. Buttons -->
    <div class="demo-section">
        <h2 class="demo-title">4. Button Components</h2>

        <div class="flex flex-wrap gap-4">
            <?php
            echo ThemeComponents::button('Primary Button', 'primary', '#');
            echo ThemeComponents::button('Secondary Button', 'secondary', '#');
            echo ThemeComponents::button('Success Button', 'success', '#');
            echo ThemeComponents::button('Danger Button', 'danger', '#');
            ?>
        </div>

        <div class="code-preview mt-4">
            <strong>Usage:</strong><br>
            echo ThemeComponents::button('Button Text', 'primary|secondary|success|danger', '/url');
        </div>
    </div>

    <!-- 5. Forms -->
    <div class="demo-section">
        <h2 class="demo-title">5. Form Components</h2>

        <?php
        echo ThemeComponents::openCard('Sample Form', '
            <form action="#" method="post" class="space-y-4">
                ' . ThemeComponents::input('text', 'player_name', 'Player Name', '', true, 'Enter player name') . '

                ' . ThemeComponents::input('email', 'email', 'Email Address', '', true, 'user@example.com') . '

                ' . ThemeComponents::select('position', 'Position', [
                    'G' => 'Guard',
                    'F' => 'Forward',
                    'C' => 'Center'
                ], '', true) . '

                ' . ThemeComponents::textarea('notes', 'Notes', '', false, 'Additional notes...') . '

                <div class="flex gap-4">
                    ' . ThemeComponents::button('Submit', 'primary', '#') . '
                    ' . ThemeComponents::button('Cancel', 'secondary', '#') . '
                </div>
            </form>
        ');
        ?>

        <div class="code-preview">
            <strong>Usage:</strong><br>
            echo ThemeComponents::input('type', 'name', 'Label', 'value', required, 'placeholder');<br>
            echo ThemeComponents::select('name', 'Label', ['key' => 'value'], 'selected', required);<br>
            echo ThemeComponents::textarea('name', 'Label', 'value', required, 'placeholder');
        </div>
    </div>

    <!-- 6. Sidebar Boxes -->
    <div class="demo-section">
        <h2 class="demo-title">6. Sidebar Box Components</h2>

        <div class="max-w-xs">
            <?php
            echo ThemeComponents::sidebarBox('Quick Links', '
                <ul class="space-y-2">
                    <li><a href="#" class="text-ibl-link hover:underline">My Team</a></li>
                    <li><a href="#" class="text-ibl-link hover:underline">Free Agency</a></li>
                    <li><a href="#" class="text-ibl-link hover:underline">Trading Block</a></li>
                    <li><a href="#" class="text-ibl-link hover:underline">Depth Chart</a></li>
                    <li><a href="#" class="text-ibl-link hover:underline">League Standings</a></li>
                </ul>
            ');
            ?>
        </div>

        <div class="code-preview">
            <strong>Usage:</strong><br>
            echo ThemeComponents::sidebarBox('Box Title', '&lt;p&gt;Box content&lt;/p&gt;');
        </div>
    </div>

    <!-- 7. Mobile Menu Preview -->
    <div class="demo-section">
        <h2 class="demo-title">7. Mobile Menu (Responsive)</h2>

        <?php
        echo ThemeComponents::alert('info',
            '📱 <strong>Mobile Menu:</strong> Click the hamburger icon (☰) in the top-left corner on mobile devices to see the categorized navigation menu.
            It automatically organizes all sidebar links into Team, Stats, Site, and Account categories.'
        );

        try {
            $menuCounts = $menuBuilder->getLinkCounts();
        } catch (Exception $e) {
            $menuCounts = ['team' => 0, 'stats' => 0, 'site' => 0, 'account' => 0, 'other' => 0];
        }
        echo ThemeComponents::openCard('Mobile Menu Statistics', '
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="font-semibold text-gray-700">Team Links:</dt>
                    <dd class="text-2xl text-ibl-link">' . $menuCounts['team'] . '</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-700">Stats Links:</dt>
                    <dd class="text-2xl text-ibl-link">' . $menuCounts['stats'] . '</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-700">Site Links:</dt>
                    <dd class="text-2xl text-ibl-link">' . $menuCounts['site'] . '</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-700">Account Links:</dt>
                    <dd class="text-2xl text-ibl-link">' . $menuCounts['account'] . '</dd>
                </div>
            </dl>
        ');
        ?>

        <div class="code-preview">
            <strong>To test mobile menu:</strong><br>
            1. Resize your browser window to mobile size (< 768px)<br>
            2. Click the hamburger icon (☰) in the header<br>
            3. See categorized navigation with all sidebar links
        </div>
    </div>

    <!-- 8. Responsive Grid Layout -->
    <div class="demo-section">
        <h2 class="demo-title">8. Responsive Grid Layout</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php
            for ($i = 1; $i <= 4; $i++) {
                echo '<div class="bg-white border border-gray-300 rounded-lg p-4 text-center">';
                echo '<div class="text-3xl font-bold text-ibl-link">' . $i . '</div>';
                echo '<p class="text-sm text-gray-600 mt-2">Grid Item ' . $i . '</p>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="code-preview mt-4">
            <strong>Responsive Behavior:</strong><br>
            • Mobile (< 640px): 1 column<br>
            • Tablet (640px - 1023px): 2 columns<br>
            • Desktop (≥ 1024px): 4 columns
        </div>
    </div>

    <!-- 9. Component Summary -->
    <div class="demo-section">
        <h2 class="demo-title">9. Integration Summary</h2>

        <?php
        echo ThemeComponents::openCard('Available Components', '
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-lg mb-2">View\ThemeComponents</h3>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        <li><code>card($title, $content)</code> - Card with title and content</li>
                        <li><code>table($content)</code> - Styled table</li>
                        <li><code>alert($type, $message)</code> - Success/info/warning/error alerts</li>
                        <li><code>button($text, $style, $url)</code> - Styled buttons</li>
                        <li><code>input($type, $name, $label, ...)</code> - Form inputs</li>
                        <li><code>select($name, $label, $options, ...)</code> - Dropdowns</li>
                        <li><code>textarea($name, $label, ...)</code> - Text areas</li>
                        <li><code>sidebarBox($title, $content)</code> - Sidebar boxes</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold text-lg mb-2">View\BladeRenderer</h3>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        <li><code>render($view, $data)</code> - Render Blade templates</li>
                        <li>Supports: {{ }}, {!! !!}, @if, @foreach, @php</li>
                        <li>Templates: partials/header.blade.php, partials/footer.blade.php</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold text-lg mb-2">Navigation\MobileMenuBuilder</h3>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        <li><code>build()</code> - Extract and categorize all sidebar links</li>
                        <li><code>render()</code> - Generate mobile menu HTML</li>
                        <li>Auto-categorizes: Team, Stats, Site, Account, Other</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold text-lg mb-2">Auth\LaravelAuthBridge</h3>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        <li>Modern authentication replacing PHP-Nuke</li>
                        <li>MD5 → bcrypt password migration</li>
                        <li>Role-based permissions (spectator, owner, commissioner)</li>
                        <li>Ready to integrate (migration already run)</li>
                    </ul>
                </div>
            </div>
        ', 'bg-blue-50 border-blue-300');
        ?>
    </div>

</div>

<!-- Render Footer -->
<?php
try {
    $mobileMenuHtml = '';
    try {
        $mobileMenuHtml = $menuBuilder->render();
    } catch (Exception $e) {
        $mobileMenuHtml = '<p class="text-gray-500 text-sm">Mobile menu not available (blocks directory not accessible)</p>';
    }

    echo $bladeRenderer->render('partials/footer', [
        'mobileMenuHtml' => $mobileMenuHtml,
    ]);
} catch (Exception $e) {
    echo "<div style='background: #fee; border: 2px solid #f00; padding: 1rem; margin: 1rem;'>";
    echo "<strong>Footer Render Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

</body>
</html>
