<?php

declare(strict_types=1);

/**
 * Terms Module - static Terms of Service page.
 *
 * Linked from the Google OAuth consent screen, so the URL must stay stable.
 *
 * @see Legal\LegalView::renderTerms()
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$pagetitle = "- Terms of Service";

PageLayout\PageLayout::header();
echo \Module\ModuleServices::current()->factory(\Module\Factories\LegalFactory::class)->view()->renderTerms();
PageLayout\PageLayout::footer();
