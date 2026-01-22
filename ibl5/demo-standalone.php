<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBL5 - New Theme Demo (Standalone)</title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="themes/IBL/dist/app.css">

    <!-- Alpine.js -->
    <script defer src="node_modules/alpinejs/dist/cdn.min.js"></script>

    <style>
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

<!-- Mobile-First Header -->
<header class="bg-white shadow-md" x-data="{ mobileMenuOpen: false }">
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <!-- Mobile Menu Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-gray-600 hover:text-gray-900">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <!-- Logo/Title -->
            <h1 class="text-2xl font-bold text-ibl-link">IBL5 Demo</h1>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex space-x-4">
                <a href="#" class="text-gray-600 hover:text-ibl-link">Home</a>
                <a href="#" class="text-gray-600 hover:text-ibl-link">Teams</a>
                <a href="#" class="text-gray-600 hover:text-ibl-link">Stats</a>
                <a href="#" class="text-gray-600 hover:text-ibl-link">Account</a>
            </nav>

            <!-- League Switcher -->
            <select class="border border-gray-300 rounded px-2 py-1 text-sm">
                <option>IBL</option>
                <option>Olympics</option>
            </select>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden mt-4 border-t pt-4">
            <div class="space-y-2">
                <a href="#" class="block text-gray-600 hover:text-ibl-link py-2">Home</a>
                <a href="#" class="block text-gray-600 hover:text-ibl-link py-2">Teams</a>
                <a href="#" class="block text-gray-600 hover:text-ibl-link py-2">Stats</a>
                <a href="#" class="block text-gray-600 hover:text-ibl-link py-2">Account</a>
            </div>
        </div>
    </div>
</header>

<!-- Main Content Area -->
<div class="container mx-auto px-4 py-8">

    <!-- Page Title -->
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-ibl-link mb-2">IBL5 New Theme Demo (Standalone)</h1>
        <p class="text-gray-600">Preview of Tailwind CSS components and modern styling</p>
        <div class="mt-4 p-4 bg-green-50 border border-green-300 rounded">
            <p class="text-green-800">✅ <strong>Success!</strong> This page loaded without redirects. All components shown below use pure Tailwind CSS.</p>
        </div>
    </div>

    <!-- 1. Cards -->
    <div class="demo-section">
        <h2 class="demo-title">1. Card Components</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Card 1 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-ibl-link text-white px-4 py-3 font-semibold">Team Stats</div>
                <div class="p-4">
                    <p class="mb-4"><strong>Los Angeles Lakers</strong></p>
                    <ul class="space-y-2 text-sm">
                        <li>Record: 45-37 (3rd in Western Conference)</li>
                        <li>PPG: 112.5 (8th in league)</li>
                        <li>RPG: 44.2 (12th in league)</li>
                        <li>APG: 26.8 (5th in league)</li>
                    </ul>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-ibl-link text-white px-4 py-3 font-semibold">Recent Transactions</div>
                <div class="p-4">
                    <ul class="space-y-2 text-sm">
                        <li>✅ <strong>Trade:</strong> Acquired G Smith from Boston</li>
                        <li>📝 <strong>Extension:</strong> Signed C Johnson to 3-year deal</li>
                        <li>🔄 <strong>Waiver:</strong> Claimed F Davis off waivers</li>
                    </ul>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-ibl-link text-white px-4 py-3 font-semibold">Upcoming Games</div>
                <div class="p-4">
                    <ul class="space-y-2 text-sm">
                        <li>01/21: vs. Boston Celtics (7:30 PM ET)</li>
                        <li>01/23: @ Miami Heat (8:00 PM ET)</li>
                        <li>01/25: vs. Phoenix Suns (7:30 PM ET)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Tables -->
    <div class="demo-section">
        <h2 class="demo-title">2. Table Components</h2>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Player</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">PPG</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">RPG</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">APG</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">LeBron James</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">F</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">27.2</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">7.5</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">7.3</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">Anthony Davis</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">C</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">24.0</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">9.9</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">3.1</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">D'Angelo Russell</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">G</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">17.8</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">2.8</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">6.2</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Alerts -->
    <div class="demo-section">
        <h2 class="demo-title">3. Alert Components</h2>

        <div class="space-y-4">
            <!-- Success Alert -->
            <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded">
                ✅ Trade successful! Your offer has been accepted.
            </div>

            <!-- Info Alert -->
            <div class="bg-blue-50 border border-blue-300 text-blue-800 px-4 py-3 rounded">
                ℹ️ The next simulation will run on January 21, 2026 at 10:00 PM ET.
            </div>

            <!-- Warning Alert -->
            <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded">
                ⚠️ You are $2.5M over the salary cap. Please make roster adjustments.
            </div>

            <!-- Error Alert -->
            <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded">
                ❌ Invalid depth chart submission: You must have at least 5 players at each position.
            </div>
        </div>
    </div>

    <!-- 4. Buttons -->
    <div class="demo-section">
        <h2 class="demo-title">4. Button Components</h2>

        <div class="flex flex-wrap gap-4">
            <a href="#" class="inline-block bg-ibl-link hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Primary Button</a>
            <a href="#" class="inline-block bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded">Secondary Button</a>
            <a href="#" class="inline-block bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded">Success Button</a>
            <a href="#" class="inline-block bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded">Danger Button</a>
        </div>
    </div>

    <!-- 5. Forms -->
    <div class="demo-section">
        <h2 class="demo-title">5. Form Components</h2>

        <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl">
            <h3 class="text-xl font-semibold mb-4">Sample Form</h3>
            <form action="#" method="post" class="space-y-4">
                <!-- Text Input -->
                <div>
                    <label for="player_name" class="block text-sm font-medium text-gray-700 mb-1">Player Name <span class="text-red-500">*</span></label>
                    <input type="text" id="player_name" name="player_name" required placeholder="Enter player name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-ibl-link">
                </div>

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="user@example.com" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-ibl-link">
                </div>

                <!-- Select -->
                <div>
                    <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position <span class="text-red-500">*</span></label>
                    <select id="position" name="position" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-ibl-link">
                        <option value="">Select position...</option>
                        <option value="G">Guard</option>
                        <option value="F">Forward</option>
                        <option value="C">Center</option>
                    </select>
                </div>

                <!-- Textarea -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Additional notes..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-ibl-link"></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex gap-4">
                    <button type="submit" class="bg-ibl-link hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Submit</button>
                    <button type="reset" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. Responsive Grid -->
    <div class="demo-section">
        <h2 class="demo-title">6. Responsive Grid Layout</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-300 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-ibl-link">1</div>
                <p class="text-sm text-gray-600 mt-2">Grid Item 1</p>
            </div>
            <div class="bg-white border border-gray-300 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-ibl-link">2</div>
                <p class="text-sm text-gray-600 mt-2">Grid Item 2</p>
            </div>
            <div class="bg-white border border-gray-300 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-ibl-link">3</div>
                <p class="text-sm text-gray-600 mt-2">Grid Item 3</p>
            </div>
            <div class="bg-white border border-gray-300 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-ibl-link">4</div>
                <p class="text-sm text-gray-600 mt-2">Grid Item 4</p>
            </div>
        </div>

        <div class="code-preview mt-4">
            <strong>Responsive Behavior:</strong><br>
            • Mobile (< 640px): 1 column<br>
            • Tablet (640px - 1023px): 2 columns<br>
            • Desktop (≥ 1024px): 4 columns
        </div>
    </div>

    <!-- 7. Integration Guide -->
    <div class="demo-section">
        <h2 class="demo-title">7. Using ThemeComponents in Your Code</h2>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-lg mb-2">PHP Usage (Copy-Paste Ready)</h3>
                    <div class="code-preview">
&lt;?php<br>
use View\ThemeComponents;<br>
<br>
// Card - Replaces OpenTable/CloseTable<br>
echo ThemeComponents::openCard('Card Title');<br>
echo '&lt;p&gt;Card content here&lt;/p&gt;';<br>
echo ThemeComponents::closeCard();<br>
<br>
// Alert - Success/Info/Warning/Error<br>
echo ThemeComponents::alert('success', 'Operation successful!');<br>
echo ThemeComponents::alert('error', 'Please fix the errors');<br>
<br>
// Button - With URL link<br>
echo ThemeComponents::button('Submit', 'primary', '/submit.php');<br>
<br>
// Table - Wrap your table HTML<br>
echo ThemeComponents::openTable();<br>
echo '&lt;thead&gt;&lt;tr&gt;&lt;th&gt;Name&lt;/th&gt;&lt;/tr&gt;&lt;/thead&gt;';<br>
echo '&lt;tbody&gt;&lt;tr&gt;&lt;td&gt;Value&lt;/td&gt;&lt;/tr&gt;&lt;/tbody&gt;';<br>
echo ThemeComponents::closeTable();<br>
<br>
// Form Input<br>
echo ThemeComponents::input('text', 'username', 'Username', '', true, 'Enter username');<br>
<br>
// Select Dropdown<br>
echo ThemeComponents::select('position', 'Position', [<br>
&nbsp;&nbsp;&nbsp;&nbsp;'G' =&gt; 'Guard',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'F' =&gt; 'Forward',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'C' =&gt; 'Center'<br>
], '', true);<br>
<br>
// Sidebar Box - Replaces themesidebox()<br>
echo ThemeComponents::sidebarBox('Quick Links', '&lt;ul&gt;&lt;li&gt;Link 1&lt;/li&gt;&lt;/ul&gt;');<br>
?&gt;
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-lg mb-2">Next Steps</h3>
                    <ul class="list-disc list-inside space-y-2">
                        <li>Replace OpenTable/CloseTable with ThemeComponents::openCard()</li>
                        <li>Replace themesidebox() with ThemeComponents::sidebarBox()</li>
                        <li>Update theme.php to use BladeRenderer for header/footer</li>
                        <li>Deploy Laravel Auth system (migrations already run)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Footer -->
<footer class="bg-gray-800 text-white py-8 mt-12">
    <div class="container mx-auto px-4 text-center">
        <p>IBL5 - Internet Basketball League</p>
        <p class="text-sm text-gray-400 mt-2">Powered by Tailwind CSS &amp; Alpine.js</p>
    </div>
</footer>

</body>
</html>
