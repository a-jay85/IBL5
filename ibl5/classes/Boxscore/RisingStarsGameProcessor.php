<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\GameTypeProcessorInterface;
use JsbParser\ScoFileParser;
use Season\Season;

/**
 * RisingStarsGameProcessor - Processes the Rising Stars All-Star Weekend game
 *
 * @see GameTypeProcessorInterface
 */
class RisingStarsGameProcessor implements GameTypeProcessorInterface
{
    private const RISING_STARS_VISITOR_TID = 40;
    private const RISING_STARS_HOME_TID = 41;

    public function __construct(
        private GameUpsertResolver $resolver,
        private GameLineWriter $writer,
    ) {
    }

    /**
     * @see GameTypeProcessorInterface::process()
     *
     * Note: $seasonPhase and $league are deliberately ignored — the Rising Stars game
     * always uses the hardcoded phase 'Regular Season/Playoffs' with no league argument.
     */
    public function process(string $line, int $seasonEndingYear, string $seasonPhase, string $league): array
    {
        $gameInfoLine = ScoFileParser::extractGameInfo($line);
        $boxscoreGameInfo = Boxscore::withGameInfoLine($gameInfoLine, $seasonEndingYear, 'Regular Season/Playoffs');
        $boxscoreGameInfo->overrideGameContext(
            sprintf('%d-%02d-%02d', $seasonEndingYear, Season::IBL_ALL_STAR_MONTH, Season::IBL_RISING_STARS_GAME_DAY),
            self::RISING_STARS_VISITOR_TID,
            self::RISING_STARS_HOME_TID,
            1,
        );

        $upsertAction = $this->resolver->resolve($boxscoreGameInfo);

        if ($upsertAction === 'skip') {
            return ['action' => 'skip', 'linesProcessed' => 0, 'messages' => ['Rising Stars Game: already exists, skipped.']];
        }

        $linesProcessed = $this->writer->write($line, $boxscoreGameInfo, 'Rookies', 'Sophomores');

        /** @var list<string> $messages */
        $messages = [];
        if ($linesProcessed > 0) {
            $action = $upsertAction === 'update' ? 'updated' : 'inserted';
            $messages[] = "Rising Stars Game: {$action} ({$linesProcessed} lines).";
        }

        return ['action' => $upsertAction, 'linesProcessed' => $linesProcessed, 'messages' => $messages];
    }
}
