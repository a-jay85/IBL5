<?php

declare(strict_types=1);

namespace Navigation\Views;

use Utilities\HtmlSanitizer;

/**
 * Renders the inline login form for both desktop and mobile navigation.
 * The forms are structurally identical; only CSS sizing classes differ.
 */
class LoginFormView
{
    /**
     * Render the login form.
     *
     * @param 'desktop'|'mobile' $variant
     */
    public function render(string $variant, ?string $requestUri): string
    {
        $isDesktop = $variant === 'desktop';

        // Sizing classes differ between desktop and mobile
        $containerClass = $isDesktop
            ? 'px-4 pt-4 pb-3'
            : 'px-5 pt-4 pb-4 bg-gradient-to-b from-accent-500/10 to-transparent';
        $labelClass = $isDesktop
            ? 'block text-base font-semibold tracking-widest uppercase text-gray-400 mb-1.5'
            : 'block text-base font-semibold tracking-widest uppercase text-gray-400 mb-2';
        $iconSize = $isDesktop ? 'w-4 h-4' : 'w-5 h-5';
        $inputClass = 'nav-login-input';
        $checkboxSize = $isDesktop ? 'w-4 h-4' : 'w-5 h-5';
        $checkmarkSize = $isDesktop ? 'w-2.5 h-2.5' : 'w-3 h-3';
        $rememberTextSize = $isDesktop ? 'text-sm' : 'text-base';
        $rememberGap = $isDesktop ? 'gap-2.5' : 'gap-3';
        $buttonClass = 'nav-login-btn';
        $idPrefix = $isDesktop ? 'nav' : 'mobile-nav';

        $currentQuery = parse_url($requestUri ?? '', PHP_URL_QUERY);
        $safeQuery = HtmlSanitizer::safeHtmlOutput(is_string($currentQuery) ? $currentQuery : '');

        ob_start();
        ?>
        <div class="<?= $containerClass ?>">
            <form action="modules.php?name=YourAccount" method="post" hx-boost="false" class="space-y-3">
                <div>
                    <label for="<?= $idPrefix ?>-username" class="<?= $labelClass ?>">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="<?= $iconSize ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="username"
                            id="<?= $idPrefix ?>-username"
                            maxlength="25"
                            required
                            placeholder="Enter username"
                            class="<?= $inputClass ?>"
                        >
                    </div>
                </div>

                <div>
                    <label for="<?= $idPrefix ?>-password" class="<?= $labelClass ?>">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="<?= $iconSize ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            name="user_password"
                            id="<?= $idPrefix ?>-password"
                            maxlength="20"
                            required
                            placeholder="Enter password"
                            class="<?= $inputClass ?>"
                        >
                    </div>
                </div>

                <label class="flex items-center <?= $rememberGap ?> cursor-pointer group/remember py-0.5">
                    <span class="relative inline-flex items-center justify-center <?= $checkboxSize ?> shrink-0">
                        <input
                            type="checkbox"
                            name="remember_me"
                            value="1"
                            class="peer nav-login-checkbox <?= $checkboxSize ?>"
                        >
                        <svg class="absolute <?= $checkmarkSize ?> text-white pointer-events-none opacity-0 peer-checked:opacity-100 transition-opacity duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="<?= $rememberTextSize ?> text-gray-400 group-hover/remember:text-gray-300 transition-colors duration-150 select-none">Remember me</span>
                </label>

                <input type="hidden" name="op" value="login">
                <input type="hidden" name="redirect_query" value="<?= $safeQuery ?>">
                <?= \Utilities\CsrfGuard::generateToken('login') ?>

                <button type="submit" class="<?= $buttonClass ?>">
                    Login
                </button>
            </form>
        </div>
        <?php
        if ($isDesktop) {
            ?>
        <div class="border-t border-white/10 mx-4"></div>
            <?php
        }

        return (string) ob_get_clean();
    }
}
