<?php

declare(strict_types=1);

namespace PlrParser;

use JsbParser\PlrFileWriter;
use PlrParser\Contracts\PlrBoxScoreRepositoryInterface;
use PlrParser\Contracts\PlrSimDateInferrerInterface;

/**
 * @see PlrSimDateInferrerInterface
 */
class PlrSimDateInferrer implements PlrSimDateInferrerInterface
{
    /** Pick a reference player with at least this many minutes to make the match robust. */
    private const MIN_MINUTES_FOR_REFERENCE_PLAYER = 500;

    public function __construct(
        private readonly PlrBoxScoreRepositoryInterface $boxScoreRepository,
    ) {
    }

    /**
     * @see PlrSimDateInferrerInterface::inferBaseEndDate()
     */
    public function inferBaseEndDate(string $basePlrPath, int $seasonYear): ?string
    {
        $reference = $this->pickReferencePlayer($basePlrPath);
        if ($reference === null) {
            return null;
        }

        $cumulative = $this->boxScoreRepository->cumulativeStatsForPlayerByDate(
            $reference['pid'],
            $seasonYear,
            PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON,
        );

        foreach ($cumulative as $row) {
            if (
                $row['gp'] === $reference['gp']
                && $row['min'] === $reference['min']
                && $row['two_gm'] === $reference['two_gm']
                && $row['three_gm'] === $reference['three_gm']
                && $row['ftm'] === $reference['ftm']
            ) {
                return $row['date'];
            }
        }

        return null;
    }

    /**
     * @see PlrSimDateInferrerInterface::inferNextSimEndDate()
     */
    public function inferNextSimEndDate(string $baseEndDate, int $seasonYear, int $stepsAhead = 1): ?string
    {
        $endDates = $this->boxScoreRepository->simEndDatesForSeason($seasonYear);
        $baseIndex = array_search($baseEndDate, $endDates, true);
        if ($baseIndex === false) {
            return null;
        }
        $targetIndex = $baseIndex + $stepsAhead;
        return $endDates[$targetIndex] ?? null;
    }

    /**
     * @see PlrSimDateInferrerInterface::getBoxScoreCoverageForSeason()
     */
    public function getBoxScoreCoverageForSeason(int $seasonYear): ?string
    {
        return $this->boxScoreRepository->latestGameDate(
            $seasonYear,
            PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON,
        );
    }

    /**
     * Pick a high-minutes active player from a .plr to serve as the inference anchor.
     *
     * Returns the player with the most minutes, along with their key season stats.
     * The stats are read directly from the .plr record; later compared against
     * cumulative box-score totals to find the matching date.
     *
     * @return array{pid: int, gp: int, min: int, two_gm: int, three_gm: int, ftm: int}|null
     */
    private function pickReferencePlayer(string $basePlrPath): ?array
    {
        $content = PlrFileWriter::readFile($basePlrPath);
        $lines = PlrFileWriter::splitIntoLines($content);

        $bestPid = 0;
        $bestMin = 0;
        $bestRow = null;

        foreach ($lines as $line) {
            if (strlen($line) < PlrFileWriter::PLAYER_RECORD_LENGTH) {
                continue;
            }
            $ordinal = (int) trim(substr($line, PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL));
            $pid = (int) trim(substr($line, PlrFileWriter::OFFSET_PID, PlrFileWriter::WIDTH_PID));
            if ($pid === 0 || $ordinal > PlrFileWriter::MAX_PLAYER_ORDINAL) {
                continue;
            }

            $min = PlrFileWriter::readField($line, 'seasonMIN');
            if ($min < self::MIN_MINUTES_FOR_REFERENCE_PLAYER) {
                continue;
            }

            if ($min > $bestMin) {
                $bestMin = $min;
                $bestPid = $pid;
                $bestRow = [
                    'pid' => $pid,
                    'gp' => PlrFileWriter::readField($line, 'seasonGamesPlayed'),
                    'min' => $min,
                    'two_gm' => PlrFileWriter::readField($line, 'season2GM'),
                    'three_gm' => PlrFileWriter::readField($line, 'season3GM'),
                    'ftm' => PlrFileWriter::readField($line, 'seasonFTM'),
                ];
            }
        }

        return $bestRow;
    }
}
