<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Renders the inline login form for both desktop and mobile navigation.
 * The forms are structurally identical; only CSS sizing classes differ.
 *
 * @see \Navigation\Views\LoginFormView
 */
interface LoginFormViewInterface
{
    /**
     * Render the login form.
     *
     * @param 'desktop'|'mobile' $variant
     */
    public function render(string $variant, ?string $requestUri): string;
}
