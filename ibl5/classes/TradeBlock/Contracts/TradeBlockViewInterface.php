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
     * Owner's bulk edit form. The view consumes only `pid` and `name` from each
     * roster row (the Service passes full player rows, which satisfy this shape).
     *
     * @param list<array{pid: int, name: string, ...<string, mixed>}> $roster
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
