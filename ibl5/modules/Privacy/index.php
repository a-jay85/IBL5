<?php

declare(strict_types=1);

/**
 * Privacy Module - static Privacy Policy page.
 *
 * Linked from the Google OAuth consent screen, so the URL must stay stable.
 *
 * @see Legal\LegalView::renderPrivacy()
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$pagetitle = "- Privacy Policy";

PageLayout\PageLayout::header();
echo \Module\ModuleServices::current()->factory(\Module\Factories\LegalFactory::class)->view()->renderPrivacy();
PageLayout\PageLayout::footer();
