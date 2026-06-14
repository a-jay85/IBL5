<?php

declare(strict_types=1);

namespace CapWhatIf\Contracts;

/**
 * CapWhatIfViewInterface - Contract for the cap "what-if" sandbox view.
 *
 * Renders the GET form (waive select + signing inputs) and the baseline-vs-
 * scenario result table. All dynamic output is HTML-escaped by the concrete
 * implementation; the form carries no CSRF token because the endpoint is an
 * idempotent GET that mutates no server state.
 *
 * @phpstan-import-type ScenarioResult from CapWhatIfServiceInterface
 *
 * @see \CapWhatIf\CapWhatIfView For the concrete implementation
 */
interface CapWhatIfViewInterface
{
    /**
     * Render the cap calculator page body.
     *
     * @param ScenarioResult $scenarioData Computed baseline/scenario cap totals
     * @param list<array<string, mixed>> $rosterPlayers Owner's rostered players (for the waive select)
     * @param int $beginningYear Starting year for column labels
     * @param int $endingYear Ending year for column labels
     * @return string HTML output
     */
    public function render(array $scenarioData, array $rosterPlayers, int $beginningYear, int $endingYear): string;
}
