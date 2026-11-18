<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback\Contracts;

use TradeSuggestions\Feedback\DealKey;

interface FeedbackRepositoryInterface
{
    /**
     * Deal and core keys of every idea this team hid.
     *
     * @return array{deal: list<string>, core: list<string>}
     */
    public function hiddenKeys(int $tid): array;

    /**
     * Store one hide. A second hide of the same deal updates the row.
     * The free text is stored raw; an empty string is stored as NULL.
     */
    public function record(int $tid, int $partnerTid, DealKey $key, string $dealJson, string $reasonCodes, string $freeText): void;

    /**
     * This team's newest hide, or null when it has none.
     *
     * @return array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}|null
     */
    public function latestForTeam(int $tid): ?array;
}
