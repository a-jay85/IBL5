                {{-- Right Sidebar (hidden on mobile/tablet, shown on desktop) --}}
                <aside class="hidden lg:block space-y-4" id="right-blocks">
                    {{-- Right blocks rendered here by PHP --}}
                </aside>

            </div>{{-- End grid --}}
        </div>{{-- End container --}}

    </div>{{-- End Alpine x-data wrapper --}}

    {{-- Footer --}}
    <footer class="bg-ibl-gray-medium mt-8 py-6">
        <div class="container-ibl text-center text-sm text-gray-600">
            @if(!empty($copyright))
                {!! $copyright !!}
            @else
                <p>&copy; {{ date('Y') }} Internet Basketball League. All rights reserved.</p>
            @endif

            {{-- Footer links --}}
            <div class="mt-4 space-x-4">
                <a href="modules.php?name=Topics" class="hover:text-ibl-link">Topics</a>
                <a href="modules.php?name=Your_Account" class="hover:text-ibl-link">Your Account</a>
            </div>

            {{-- Page generation time (legacy feature) --}}
            @if(isset($generationTime))
            <p class="mt-4 text-xs text-gray-500">
                Page generated in {{ $generationTime }} seconds
            </p>
            @endif
        </div>
    </footer>

    {{-- Inline script for mobile menu dynamic content --}}
    <script>
        // Mobile menu gets links from desktop sidebars
        document.addEventListener('DOMContentLoaded', function() {
            const leftBlocks = document.getElementById('left-blocks');
            const rightBlocks = document.getElementById('right-blocks');
            const mobileContent = document.getElementById('mobile-menu-content');

            if (mobileContent && (leftBlocks || rightBlocks)) {
                // Extract links from sidebar blocks for mobile menu
                const links = [];
                const blocks = document.querySelectorAll('#left-blocks a, #right-blocks a');

                blocks.forEach(function(link) {
                    const href = link.getAttribute('href');
                    const text = link.textContent.trim();
                    if (href && text && !links.some(l => l.href === href)) {
                        links.push({ href: href, text: text });
                    }
                });

                // Group and add to mobile menu
                if (links.length > 0) {
                    let html = '<div class="nav-group"><div class="nav-group-title">Quick Links</div>';
                    links.slice(0, 20).forEach(function(link) {
                        html += '<a href="' + link.href + '" class="nav-link">' + link.text + '</a>';
                    });
                    html += '</div>';
                    mobileContent.innerHTML = html;
                }
            }
        });
    </script>

</body>
</html>
