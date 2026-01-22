{{-- IBL Theme Header - Tailwind CSS Mobile-First Layout --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sitename ?? 'IBL' }}{{ !empty($pagetitle) ? ' ' . $pagetitle : '' }}</title>

    {{-- Tailwind CSS --}}
    <link rel="stylesheet" href="/ibl5/themes/IBL/dist/app.css">

    {{-- Alpine.js for interactivity --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Legacy styles for backwards compatibility --}}
    <style>
        /* Legacy color variables */
        :root {
            --bgcolor1: {{ $bgcolor1 ?? '#EEEEEE' }};
            --bgcolor2: {{ $bgcolor2 ?? '#CCCCCC' }};
            --bgcolor3: {{ $bgcolor3 ?? '#AAAAAA' }};
            --lnkcolor: {{ $lnkcolor ?? '#336699' }};
        }
    </style>
</head>
<body class="bg-ibl-gray-light min-h-screen">
    {{-- Mobile Menu Toggle (visible on small screens only) --}}
    <div x-data="{ mobileMenuOpen: false }" class="relative">

        {{-- Top Navigation Bar --}}
        <header class="bg-ibl-gray-medium shadow-sm">
            <div class="container-ibl">
                <div class="flex items-center justify-between py-2">
                    {{-- Logo/Home Link --}}
                    <a href="index.php" class="font-bold text-lg text-gray-800 hover:text-ibl-link">
                        IBL
                    </a>

                    {{-- Mobile Menu Button --}}
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="mobile-menu-btn"
                        aria-label="Toggle menu"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>

                    {{-- Desktop Navigation --}}
                    <nav class="hidden md:flex items-center space-x-4 text-sm">
                        <a href="index.php" class="nav-link">Home</a>
                        <a href="modules.php?name=Your_Account" class="nav-link">Your Account</a>
                        <a href="modules.php?name=Topics" class="nav-link">Topics</a>

                        {{-- League Switcher --}}
                        @if(isset($currentLeague))
                        <select
                            onchange="window.location.href=this.value"
                            class="ibl-input text-sm py-1"
                        >
                            <option value="index.php?league=ibl" {{ $currentLeague === 'ibl' ? 'selected' : '' }}>IBL</option>
                            <option value="index.php?league=olympics" {{ $currentLeague === 'olympics' ? 'selected' : '' }}>Olympics</option>
                        </select>
                        @endif
                    </nav>

                    {{-- User Menu --}}
                    <div class="hidden md:flex items-center space-x-2 text-sm">
                        @if(isset($isLoggedIn) && $isLoggedIn)
                            <span class="text-gray-600">Hello {{ $username ?? 'User' }}!</span>
                            <a href="modules.php?name=Your_Account&amp;op=logout" class="ibl-button-secondary text-xs py-1 px-2">
                                Logout
                            </a>
                        @else
                            <a href="modules.php?name=Your_Account&amp;op=new_user" class="ibl-button text-xs py-1 px-2">
                                Create Account
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        {{-- Mobile Menu Overlay --}}
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false"
            class="mobile-menu-overlay md:hidden"
            style="display: none;"
        ></div>

        {{-- Mobile Menu Panel --}}
        <nav
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="mobile-menu md:hidden w-4/5 max-w-sm"
            style="display: none;"
        >
            <div class="p-4 h-full overflow-y-auto">
                {{-- Close button --}}
                <div class="flex justify-between items-center mb-6">
                    <span class="font-bold text-lg">Menu</span>
                    <button @click="mobileMenuOpen = false" class="p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Mobile menu content will be dynamically populated --}}
                <div id="mobile-menu-content">
                    {{-- Placeholder - actual links injected by MobileMenuBuilder --}}
                    <div class="nav-group">
                        <div class="nav-group-title">Navigation</div>
                        <a href="index.php" class="nav-link">Home</a>
                        <a href="modules.php?name=Your_Account" class="nav-link">Your Account</a>
                    </div>
                </div>

                {{-- User section in mobile --}}
                <div class="mt-6 pt-6 border-t border-gray-200">
                    @if(isset($isLoggedIn) && $isLoggedIn)
                        <p class="text-sm text-gray-600 mb-2">Logged in as {{ $username ?? 'User' }}</p>
                        <a href="modules.php?name=Your_Account&amp;op=logout" class="ibl-button-secondary w-full justify-center">
                            Logout
                        </a>
                    @else
                        <a href="modules.php?name=Your_Account&amp;op=new_user" class="ibl-button w-full justify-center">
                            Create Account
                        </a>
                    @endif
                </div>
            </div>
        </nav>

        {{-- Public Message Banner --}}
        @if(!empty($publicMessage))
        <div class="bg-yellow-100 border-b border-yellow-200 py-2 px-4 text-sm text-yellow-800">
            {!! $publicMessage !!}
        </div>
        @endif

        {{-- Main Content Layout --}}
        <div class="container-ibl py-4">
            <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] lg:grid-cols-[200px_1fr_200px] gap-4">

                {{-- Left Sidebar (hidden on mobile, shown on tablet+) --}}
                <aside class="hidden md:block space-y-4" id="left-blocks">
                    {{-- Left blocks rendered here by PHP --}}
