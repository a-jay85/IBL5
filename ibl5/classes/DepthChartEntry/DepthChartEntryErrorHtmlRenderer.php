<?php

declare(strict_types=1);

namespace DepthChartEntry;

use Validation\ValidationError;

/**
 * Renders depth-chart validation errors as the red centered error block the
 * submission page echoes. Pure function: no database, no session, no state.
 * Message and detail are escaped here; callers pass raw text.
 */
final class DepthChartEntryErrorHtmlRenderer
{
    /** @param list<ValidationError> $errors */
    public static function render(array $errors): string
    {
        $html = '';
        foreach ($errors as $error) {
            $message = \Security\HtmlSanitizer::safeHtmlOutput($error->message);
            $detail = \Security\HtmlSanitizer::safeHtmlOutput($error->detail);
            $html .= '<div class="text-center"><span class="text-red-500"><strong>' . $message . '</strong></span><p>' . $detail . '</p></div>';
        }
        return $html;
    }
}
