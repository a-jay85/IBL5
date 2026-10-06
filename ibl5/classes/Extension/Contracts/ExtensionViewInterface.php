<?php

declare(strict_types=1);

namespace Extension\Contracts;

/**
 * ExtensionViewInterface - Contract for extension result HTML rendering
 *
 * The extension offer form lives in Negotiation\NegotiationOfferView and the
 * submission handler is modules/Player/extension.php. This interface owns only
 * the post-redirect result banner shown on the Team page.
 *
 * @see \Extension\ExtensionView For the concrete implementation
 */
interface ExtensionViewInterface
{
    /**
     * Render a flash message banner for extension results (PRG pattern)
     *
     * @param string|null $result One of extension_error, extension_accepted,
     *                            extension_rejected; null or any other value renders nothing
     * @param string|null $msg    Player response text, escaped before output
     * @return string HTML output, empty string when there is no banner to show
     */
    public function renderResultBanner(?string $result, ?string $msg): string;
}
