<?php

declare(strict_types=1);

namespace Rivalries\Contracts;

/**
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 */
interface RivalriesViewInterface
{
    /** @param array{franchises: list<ScoredRivalry>, gms: list<ScoredRivalry>} $league */
    public function renderIndex(array $league, ?string $userTeamKey, ?string $userGm): string;

    /** @param PairPage|null $page */
    public function renderPair(?array $page, string $kind): string;

    /** @param list<ScoredRivalry> $rivals */
    public function renderGm(string $gmName, array $rivals, bool $known, ?string $userGm): string;

    public function renderEmpty(string $message): string;
}
