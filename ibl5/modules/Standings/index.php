<?php

declare(strict_types=1);

/**
 * Standings Module - Display league standings
 *
 * Dynamically generates standings from the database using the Standings module classes.
 * This replaces the previous static HTML that was stored in nuke_pages.
 *
 * @see \Standings\StandingsRepository For data access
 * @see \Standings\StandingsView For HTML rendering
 */

if (!preg_match('/modules\.php/i', $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

// The factory builds the IBL or Olympics view from the shared services
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\StandingsFactory::class);
$view = $factory->view();

// Render and output the standings
    PageLayout\PageLayout::header();
    
    echo $view->render();

    PageLayout\PageLayout::footer();
