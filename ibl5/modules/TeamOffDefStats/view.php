<?php

/**
 * League Stats Module - View Template
 *
 * Renders the league statistics page using TeamOffDefStatsView.
 * All HTML generation is delegated to the view class for better separation of concerns.
 */

Nuke\Header::header();

// Output the pre-rendered HTML from TeamOffDefStatsView
echo $leagueStatsHtml;

Nuke\Footer::footer();