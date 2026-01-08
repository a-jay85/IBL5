<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerAwardsAndNewsViewInterface - Contract for awards and news view
 * 
 * Renders player awards list and articles mentioning the player.
 */
interface PlayerAwardsAndNewsViewInterface extends PlayerViewInterface
{
    /**
     * Render awards list and articles mentioning the player
     * 
     * Shows all awards by year and a list of news articles
     * that mention the player.
     * 
     * @param string $playerName Player name to fetch awards and articles for
     * @return string Rendered HTML content
     */
    public function renderAwardsAndNews(string $playerName): string;
}
