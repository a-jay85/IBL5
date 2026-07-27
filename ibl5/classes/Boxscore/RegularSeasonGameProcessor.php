<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\GameTypeProcessorInterface;
use JsbParser\ScoFileParser;

/**
 * RegularSeasonGameProcessor - Processes a single regular-season .sco game line
 *
 * @see GameTypeProcessorInterface
 */
class RegularSeasonGameProcessor implements GameTypeProcessorInterface
{
    public function __construct(
        private GameUpsertResolver $resolver,
        private GameLineWriter $writer,
    ) {
    }

    /**
     * @see GameTypeProcessorInterface::process()
     */
    public function process(string $line, int $seasonEndingYear, string $seasonPhase, string $league): array
    {
        $gameInfoLine = ScoFileParser::extractGameInfo($line);
        $boxscoreGameInfo = Boxscore::withGameInfoLine($gameInfoLine, $seasonEndingYear, $seasonPhase, $league);

        $upsertAction = $this->resolver->resolve($boxscoreGameInfo);

        if ($upsertAction === 'skip') {
            return ['action' => 'skip', 'linesProcessed' => 0, 'messages' => []];
        }

        $linesProcessed = $this->writer->write($line, $boxscoreGameInfo);

        return ['action' => $upsertAction, 'linesProcessed' => $linesProcessed, 'messages' => []];
    }
}
