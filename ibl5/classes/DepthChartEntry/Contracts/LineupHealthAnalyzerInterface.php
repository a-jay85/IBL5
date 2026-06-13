<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * Contract for the Lineup Health Check analyzer.
 *
 * A pure function over already-loaded data: it issues no SQL and reads no
 * superglobals. The controller does all I/O and passes plain data in, which
 * makes owner-scoping structural and unit tests DB-free.
 *
 * @phpstan-type LineupWarning array{type: string, message: string}
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
interface LineupHealthAnalyzerInterface
{
    /**
     * Compute deterministic warnings about the owner's saved depth chart and roster.
     *
     * @param list<PlayerRow> $roster Owner's roster rows (already loaded by the controller)
     * @param int $totalSalary Owner team's total current salary
     * @return list<LineupWarning>
     */
    public function analyze(array $roster, int $totalSalary): array;
}
