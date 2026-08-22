<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

/**
 * GameTypeProcessorInterface - Contract for processing a single .sco game-type line
 *
 * @phpstan-type GameTypeProcessResult array{action: string, linesProcessed: int, messages: list<string>}
 */
interface GameTypeProcessorInterface
{
    /**
     * Process a single 2000-byte .sco game line
     *
     * @param string $line            The 2000-byte game line
     * @param int    $seasonEndingYear Season ending year
     * @param string $seasonPhase      Season phase (e.g. 'Regular Season/Playoffs')
     * @param string $league           League identifier (e.g. 'ibl', 'olympics')
     * @return GameTypeProcessResult
     */
    public function process(string $line, int $seasonEndingYear, string $seasonPhase, string $league): array;
}
