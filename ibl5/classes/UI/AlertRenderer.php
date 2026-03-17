<?php

declare(strict_types=1);

namespace UI;

use Utilities\HtmlSanitizer;

/**
 * AlertRenderer - Shared utility for rendering ibl-alert banners
 *
 * Replaces identical private renderResultBanner() methods across multiple view classes.
 */
class AlertRenderer
{
    /**
     * Render an alert banner from a result code using a caller-supplied banner map
     *
     * @param string|null $code Result code from query parameter
     * @param array<string, array{class: string, message: string}> $banners Map of code → CSS class + message
     * @param string|null $error Optional error message (takes priority over $code)
     * @return string HTML alert div or empty string
     */
    public static function fromCode(?string $code, array $banners, ?string $error = null): string
    {
        if ($error !== null) {
            return self::render($error, 'ibl-alert--error');
        }

        if ($code === null || !isset($banners[$code])) {
            return '';
        }

        $banner = $banners[$code];
        return self::render($banner['message'], $banner['class']);
    }

    /**
     * Render an arbitrary alert message
     *
     * @param string $message Alert message (will be HTML-escaped)
     * @param string $cssModifier CSS modifier class (e.g. 'ibl-alert--success')
     * @return string HTML alert div
     */
    public static function render(string $message, string $cssModifier): string
    {
        $safeMessage = HtmlSanitizer::safeHtmlOutput($message);
        return '<div class="ibl-alert ' . $cssModifier . '">' . $safeMessage . '</div>';
    }
}
