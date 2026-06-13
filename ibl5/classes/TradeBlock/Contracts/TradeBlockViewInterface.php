<?php

declare(strict_types=1);

namespace TradeBlock\Contracts;

use Team\Team;

/**
 * TradeBlockViewInterface - HTML rendering for the trade block.
 *
 * All dynamic output is escaped via \Security\HtmlSanitizer::e(); the browse
 * board contains NO <form> elements; the edit form emits exactly one
 * CsrfGuard::generateToken('tradeblock').
 *
 * @phpstan-import-type BrowseData from TradeBlockServiceInterface
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
interface TradeBlockViewInterface
{
    /**
     * Read-only league board.
     *
     * @param BrowseData $data
     */
    public function renderBrowse(array $data): string;

    /**
     * Owner's bulk edit form.
     *
     * @param list<PlayerRow> $roster
     * @param array<int, string> $blockPids pid => note for already-on-block players
     */
    public function renderEditForm(
        Team $team,
        array $roster,
        array $blockPids,
        string $seekingNote,
        ?string $result = null,
        ?string $error = null
    ): string;
}
