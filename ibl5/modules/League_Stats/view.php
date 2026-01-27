<?php

/**
 * League Stats Module - View Template
 *
 * Renders the league statistics page using LeagueStatsView.
 * All HTML generation is delegated to the view class for better separation of concerns.
 */

Nuke\Header::header();

// Output the pre-rendered HTML from LeagueStatsView
echo $leagueStatsHtml;

Nuke\Footer::footer();