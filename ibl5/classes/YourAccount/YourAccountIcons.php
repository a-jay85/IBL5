<?php

declare(strict_types=1);

namespace YourAccount;

/**
 * Shared SVG icon helpers used across the YourAccount auth page views.
 */
trait YourAccountIcons
{
    /**
     * Render the basketball logo icon shown above auth cards.
     */
    private function renderLogo(): string
    {
        return '<div class="auth-logo">'
            . '<div class="auth-logo__icon">'
            . '<svg viewBox="0 0 24 24" fill="currentColor">'
            . '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/>'
            . '<path d="M12 2C12 12 12 12 12 22" stroke="currentColor" stroke-width="1.5"/>'
            . '<path d="M2 12C12 12 12 12 22 12" stroke="currentColor" stroke-width="1.5"/>'
            . '<path d="M4.5 4.5C8 8 8 16 4.5 19.5" stroke="currentColor" stroke-width="1.5" fill="none"/>'
            . '<path d="M19.5 4.5C16 8 16 16 19.5 19.5" stroke="currentColor" stroke-width="1.5" fill="none"/>'
            . '</svg>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render a user icon SVG for input fields.
     */
    private function userIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'
            . '</svg>';
    }

    /**
     * Render a lock icon SVG for password fields.
     */
    private function lockIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>'
            . '</svg>';
    }

    /**
     * Render an email icon SVG for email fields.
     */
    private function emailIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>'
            . '</svg>';
    }

    /**
     * Render a checkmark icon for success status.
     */
    private function checkIcon(): string
    {
        return '<svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
            . '</svg>';
    }

    /**
     * Render an X icon for error status.
     */
    private function errorIcon(): string
    {
        return '<svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'
            . '</svg>';
    }

    /**
     * Render an info icon for informational status.
     */
    private function infoIcon(): string
    {
        return '<svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
            . '</svg>';
    }
}
