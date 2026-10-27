<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionViewInterface;
use Security\HtmlSanitizer;

/**
 * ExtensionView - HTML rendering for extension results
 *
 * Pure function over its arguments: no database handle, no session, no
 * request superglobals, no actor identity. Every dynamic value passes
 * through HtmlSanitizer::e().
 *
 * @see ExtensionViewInterface
 */
class ExtensionView implements ExtensionViewInterface
{
    /**
     * @see ExtensionViewInterface::renderResultBanner()
     */
    public function renderResultBanner(?string $result, ?string $msg): string
    {
        if ($result === null) {
            return '';
        }

        $msgSafe = HtmlSanitizer::e($msg ?? '');

        if ($result === 'extension_error') {
            return '<div role="alert" class="d-alert d-alert-error mb-4">'
                . $msgSafe
                . ' Your extension attempt was not legal and will not be recorded.'
                . '</div>';
        }

        if ($result === 'extension_accepted') {
            return '<div role="alert" class="d-alert d-alert-success mb-4">'
                . '<strong>Player response:</strong> ' . $msgSafe
                . '<br>Note from the commissioner\'s office: You have used up your successful extension for this season and may not make any more extension attempts.'
                . '</div>';
        }

        if ($result === 'extension_rejected') {
            return '<div role="alert" class="d-alert d-alert-info mb-4">'
                . '<strong>Player response:</strong> ' . $msgSafe
                . '<br>Note from the commissioner\'s office: You will be able to make another attempt next sim as you have not yet used up your successful extension for this season.'
                . '</div>';
        }

        return '';
    }
}
