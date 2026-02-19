<?php

/**
 * League Stats Module - View Template
 *
 * Renders the league statistics page using TeamOffDefStatsView.
 * All HTML generation is delegated to the view class for better separation of concerns.
 */

PageLayout\PageLayout::header();

// Output the pre-rendered HTML from TeamOffDefStatsView
echo $leagueStatsHtml;

PageLayout\PageLayout::footer();