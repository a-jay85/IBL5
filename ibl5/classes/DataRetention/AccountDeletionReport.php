<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * Outcome of one account delete or dry run.
 */
final readonly class AccountDeletionReport
{
    /**
     * @param array<string, int> $affected Rows counted (dry run) or written (confirm), keyed by AccountFootprintTarget value
     * @param list<array{teamid: int, team_name: string}> $vacatedTeams Teams whose GM slot the delete empties
     */
    public function __construct(
        public int $userId,
        public ?string $username,
        public bool $dryRun,
        public array $affected,
        public array $vacatedTeams,
    ) {
    }
}
