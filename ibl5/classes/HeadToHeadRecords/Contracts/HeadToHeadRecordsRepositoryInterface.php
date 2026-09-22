<?php

declare(strict_types=1);

namespace HeadToHeadRecords\Contracts;

/**
 * @phpstan-type Dimension 'franchises'|'teams'|'gms'
 * @phpstan-type Phase 'heat'|'regular'|'playoffs'|'all'
 * @phpstan-type Scope 'current'|'all'
 * @phpstan-type AxisEntry array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}
 * @phpstan-type MatchupRecord array{wins: int, losses: int}
 * @phpstan-type MatrixPayload array{dimension: Dimension, phase: Phase, scope: Scope, axis: list<AxisEntry>, records: array<array-key, array<array-key, MatchupRecord>>}
 */
interface HeadToHeadRecordsRepositoryInterface
{
    /** @return MatrixPayload */
    public function buildFranchisesMatrix(string $phase, string $scope): array;

    /** @return MatrixPayload */
    public function buildTeamsMatrix(string $phase, string $scope): array;

    /** @return MatrixPayload */
    public function buildGmsMatrix(string $phase, string $scope): array;

    public function currentSeasonHasGames(): bool;
}
