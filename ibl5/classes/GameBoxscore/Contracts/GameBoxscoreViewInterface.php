<?php

declare(strict_types=1);

namespace GameBoxscore\Contracts;

/**
 * Renders a single game's boxscore view-model as page HTML.
 *
 * @phpstan-import-type GameBoxscoreViewModel from GameBoxscoreServiceInterface
 */
interface GameBoxscoreViewInterface
{
    /**
     * Render the boxscore page body.
     *
     * Returns the "Game Not Found" panel when the model's `found` flag is false.
     * The 404 status itself is set by the module entry point before the page
     * chrome flushes headers — the View only produces the body.
     *
     * @param GameBoxscoreViewModel $viewModel
     * @return string HTML output
     */
    public function render(array $viewModel): string;
}
