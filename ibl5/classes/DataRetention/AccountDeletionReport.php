<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * Outcome of one account deletion, or of its dry run.
 */
final readonly class AccountDeletionReport
{
    /**
     * @param array<string, int> $affected Rows counted (dry run) or changed, keyed by AccountFootprintTarget value
     * @param list<array{teamid: int, team_name: string}> $vacatedTeams Teams whose GM slot the account held
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
