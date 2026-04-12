<?php

declare(strict_types=1);

namespace PlrParser;

use PlrParser\PlrFileWriter;
use PlrParser\Contracts\PlrBoxScoreRepositoryInterface;
use PlrParser\Contracts\PlrReconstructionServiceInterface;

/**
 * Reconstructs a missing .plr snapshot using ibl_box_scores as the source of truth.
 *
 * Why not .car? Empirically, the .car file regenerates only at season end — every mid-season
 * snapshot in a given season has byte-identical .car contents (verified via MD5 across the
 * 06-07 sim05 / sim06 / sim07 snapshots, which all hash to 59dff7...). So .car has no
 * in-progress data; box scores are the only source.
 *
 * Per-player records are matched by pid (the .plr stores the database pid at offset 38,
 * width 6) — no name-based matching needed.
 *
 * Reconstruction runs in three passes per player:
 *   1. Regular-season stats (144-207) from game_type=1 sums
 *   2. Playoff-season stats (208-267) from game_type=2 sums
 *   3. Single-season + career-best highs (341-435) from MAX(...) + DD/TD counts
 *
 * Career totals (437-511) are intentionally **not** updated. Empirically verified — the .plr
 * format freezes career totals at season start and only recomputes them at season end
 * (sim05/sim07 real .plr files carry identical career_gp / career_min for every player).
 * Rebuilding career totals mid-season would produce values that never existed in any real
 * snapshot. For end-of-season reconstruction this would need to change.
 */
class PlrReconstructionService implements PlrReconstructionServiceInterface
{
    public function __construct(
        private readonly PlrBoxScoreRepositoryInterface $boxScoreRepository,
    ) {
    }

    /**
     * Maps PlrFileWriter regular-season field names to PlrBoxScoreRepository stats-row keys.
     *
     * `seasonGamesStarted` is not in this map — box scores don't record starter vs. bench,
     * so it gets a dedicated heuristic pass (see deriveSeasonGamesStarted()).
     */
    private const REGULAR_SEASON_FIELDS = [
        'seasonGamesPlayed' => 'gp',
        'seasonMIN' => 'min',
        'season2GM' => 'two_gm',
        'season2GA' => 'two_ga',
        'seasonFTM' => 'ftm',
        'seasonFTA' => 'fta',
        'season3GM' => 'three_gm',
        'season3GA' => 'three_ga',
        'seasonORB' => 'orb',
        'seasonDRB' => 'drb',
        'seasonAST' => 'ast',
        'seasonSTL' => 'stl',
        'seasonTVR' => 'tov',
        'seasonBLK' => 'blk',
        'seasonPF' => 'pf',
    ];

    /**
     * Playoff-season field map (same stats, game_type=2).
     */
    private const PLAYOFF_SEASON_FIELDS = [
        'playoffSeasonGP' => 'gp',
        'playoffSeasonMIN' => 'min',
        'playoffSeason2GM' => 'two_gm',
        'playoffSeason2GA' => 'two_ga',
        'playoffSeasonFTM' => 'ftm',
        'playoffSeasonFTA' => 'fta',
        'playoffSeason3GM' => 'three_gm',
        'playoffSeason3GA' => 'three_ga',
        'playoffSeasonORB' => 'orb',
        'playoffSeasonDRB' => 'drb',
        'playoffSeasonAST' => 'ast',
        'playoffSeasonSTL' => 'stl',
        'playoffSeasonTVR' => 'tov',
        'playoffSeasonBLK' => 'blk',
        'playoffSeasonPF' => 'pf',
    ];

    /**
     * Season-highs field map — maps PlrFileWriter high fields to getSingleGameMaximumsThroughDate() keys.
     * These five get their career-best counterpart updated via max() as well.
     */
    private const SEASON_HIGH_FIELDS = [
        'seasonHighPTS' => ['high_pts', 'careerSeasonHighPTS'],
        'seasonHighREB' => ['high_reb', 'careerSeasonHighREB'],
        'seasonHighAST' => ['high_ast', 'careerSeasonHighAST'],
        'seasonHighSTL' => ['high_stl', 'careerSeasonHighSTL'],
        'seasonHighBLK' => ['high_blk', 'careerSeasonHighBLK'],
        'seasonHighDoubleDoubles' => ['doubles', 'careerSeasonHighDoubleDoubles'],
        'seasonHighTripleDoubles' => ['triples', 'careerSeasonHighTripleDoubles'],
    ];

    /**
     * Playoff-high field map — same structure but sourced from game_type=2 maximums.
     * No double/triple-double tracking for playoffs (the .plr format doesn't have the fields).
     */
    private const PLAYOFF_HIGH_FIELDS = [
        'seasonPlayoffHighPTS' => ['high_pts', 'careerPlayoffHighPTS'],
        'seasonPlayoffHighREB' => ['high_reb', 'careerPlayoffHighREB'],
        'seasonPlayoffHighAST' => ['high_ast', 'careerPlayoffHighAST'],
        'seasonPlayoffHighSTL' => ['high_stl', 'careerPlayoffHighSTL'],
        'seasonPlayoffHighBLK' => ['high_blk', 'careerPlayoffHighBLK'],
    ];

    /**
     * @see PlrReconstructionServiceInterface::reconstruct()
     */
    public function reconstruct(
        string $basePlrPath,
        int $seasonYear,
        string $targetEndDate,
        string $outputPlrPath,
    ): PlrReconstructionResult {
        $result = new PlrReconstructionResult();

        $content = PlrFileWriter::readFile($basePlrPath);
        $inputSize = strlen($content);
        $lines = PlrFileWriter::splitIntoLines($content);
        $result->addMessage('Loaded base .plr: ' . $inputSize . ' bytes, ' . count($lines) . ' lines');

        $regularStatsByPid = $this->boxScoreRepository->sumStatsByGameTypeThroughDate(
            $seasonYear,
            PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON,
            $targetEndDate,
        );
        $playoffStatsByPid = $this->boxScoreRepository->sumStatsByGameTypeThroughDate(
            $seasonYear,
            PlrBoxScoreRepositoryInterface::GAME_TYPE_PLAYOFFS,
            $targetEndDate,
        );
        $regularHighsByPid = $this->boxScoreRepository->getSingleGameMaximumsThroughDate(
            $seasonYear,
            PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON,
            $targetEndDate,
        );
        $playoffHighsByPid = $this->boxScoreRepository->getSingleGameMaximumsThroughDate(
            $seasonYear,
            PlrBoxScoreRepositoryInterface::GAME_TYPE_PLAYOFFS,
            $targetEndDate,
        );
        $result->addMessage(sprintf(
            'Loaded box-score aggregates through %s: %d regular / %d playoff players, %d / %d highs rows',
            $targetEndDate,
            count($regularStatsByPid),
            count($playoffStatsByPid),
            count($regularHighsByPid),
            count($playoffHighsByPid),
        ));

        foreach ($lines as $lineIndex => $line) {
            if (strlen($line) < PlrFileWriter::PLAYER_RECORD_LENGTH) {
                continue;
            }

            $ordinal = (int) trim(substr($line, PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL));
            $pid = (int) trim(substr($line, PlrFileWriter::OFFSET_PID, PlrFileWriter::WIDTH_PID));
            if ($pid === 0 || $ordinal > PlrFileWriter::MAX_PLAYER_ORDINAL) {
                continue;
            }

            $changes = [];

            // Pass 1 — regular-season stats + games-started heuristic
            $regularStats = $regularStatsByPid[$pid] ?? null;
            $changes = array_merge(
                $changes,
                $regularStats !== null
                    ? $this->buildSeasonStatsChangeSet($line, $regularStats, self::REGULAR_SEASON_FIELDS)
                    : $this->buildZeroedChangeSet($line, self::REGULAR_SEASON_FIELDS),
            );
            $changes = array_merge($changes, $this->buildGamesStartedChangeSet($line, $changes));

            // Pass 2 — playoff-season stats
            $playoffStats = $playoffStatsByPid[$pid] ?? null;
            $changes = array_merge(
                $changes,
                $playoffStats !== null
                    ? $this->buildSeasonStatsChangeSet($line, $playoffStats, self::PLAYOFF_SEASON_FIELDS)
                    : $this->buildZeroedChangeSet($line, self::PLAYOFF_SEASON_FIELDS),
            );

            // Career totals (437-511) are intentionally preserved from base — see class docblock.

            // Pass 3 — season highs + career best highs
            $regularHighs = $regularHighsByPid[$pid] ?? null;
            $changes = array_merge(
                $changes,
                $regularHighs !== null
                    ? $this->buildHighsChangeSet($line, $regularHighs, self::SEASON_HIGH_FIELDS)
                    : $this->buildZeroedHighsChangeSet($line, self::SEASON_HIGH_FIELDS),
            );
            $playoffHighs = $playoffHighsByPid[$pid] ?? null;
            $changes = array_merge(
                $changes,
                $playoffHighs !== null
                    ? $this->buildHighsChangeSet($line, $playoffHighs, self::PLAYOFF_HIGH_FIELDS)
                    : $this->buildZeroedHighsChangeSet($line, self::PLAYOFF_HIGH_FIELDS),
            );

            if ($changes === []) {
                $result->playersUnchanged++;
                continue;
            }

            $lines[$lineIndex] = PlrFileWriter::applyChangesToRecord($line, $changes);
            $result->playersUpdated++;
        }

        $result->addMessage('Updated ' . $result->playersUpdated . ' player records');
        $result->addMessage('Left ' . $result->playersUnchanged . ' player records unchanged');

        // Pass 4 — franchise team-row reconstruction (regular-season + playoff totals)
        $teamRegularByTid = $this->boxScoreRepository->sumTeamRegularSeasonStatsThroughDate(
            $seasonYear,
            $targetEndDate,
        );
        $teamPlayoffByTid = $this->boxScoreRepository->sumTeamPlayoffStatsThroughDate(
            $seasonYear,
            $targetEndDate,
        );
        $result->addMessage(sprintf(
            'Loaded team box-score aggregates: %d regular / %d playoff teams',
            count($teamRegularByTid),
            count($teamPlayoffByTid),
        ));

        foreach ($lines as $lineIndex => $line) {
            $lineLen = strlen($line);
            if ($lineLen < PlrTeamRowLayout::FRANCHISE_ROW_MIN_LENGTH
                || $lineLen > PlrTeamRowLayout::FRANCHISE_ROW_MAX_LENGTH
            ) {
                continue;
            }

            $ordinal = (int) trim(substr($line, PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL));
            if (!PlrTeamRowLayout::isFranchiseOrdinal($ordinal)) {
                continue;
            }

            $teamId = $ordinal - PlrTeamRowLayout::FIRST_TEAM_ORDINAL + 1;
            $regularStats = $teamRegularByTid[$teamId] ?? null;
            $playoffStats = $teamPlayoffByTid[$teamId] ?? null;

            if ($regularStats === null && $playoffStats === null) {
                $result->teamsUnchanged++;
                continue;
            }

            $newRow = $line;
            if ($regularStats !== null) {
                $newRow = PlrTeamRowReconstructor::applyRegularSeasonStats($newRow, $regularStats);
            }
            if ($playoffStats !== null) {
                $newRow = PlrTeamRowReconstructor::applyPlayoffSeasonStats($newRow, $playoffStats);
            }

            if ($newRow === $line) {
                $result->teamsUnchanged++;
            } else {
                $lines[$lineIndex] = $newRow;
                $result->teamsUpdated++;
            }
        }

        $result->addMessage('Updated ' . $result->teamsUpdated . ' team rows');
        $result->addMessage('Left ' . $result->teamsUnchanged . ' team rows unchanged');

        $output = PlrFileWriter::assembleFile($lines);
        if (strlen($output) !== $inputSize) {
            $result->addError('Output size (' . strlen($output)
                . ') does not match input size (' . $inputSize . ')');
            return $result;
        }

        PlrFileWriter::writeFile($output, $outputPlrPath);
        $result->bytesWritten = strlen($output);
        $result->addMessage('Wrote ' . $result->bytesWritten . ' bytes to ' . $outputPlrPath);

        return $result;
    }

    /**
     * Build a change set over a given field map from box-score aggregates.
     *
     * @param array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int} $stats
     * @param array<string, string> $fieldMap
     * @return array<string, int>
     */
    private function buildSeasonStatsChangeSet(string $line, array $stats, array $fieldMap): array
    {
        $changes = [];
        foreach ($fieldMap as $plrField => $statsField) {
            $oldValue = PlrFileWriter::readField($line, $plrField);
            /** @var int $newValue */
            $newValue = $stats[$statsField];
            if ($oldValue !== $newValue) {
                $changes[$plrField] = $newValue;
            }
        }
        return $changes;
    }

    /**
     * Zero out every field in the given field map.
     *
     * @param array<string, string> $fieldMap
     * @return array<string, int>
     */
    private function buildZeroedChangeSet(string $line, array $fieldMap): array
    {
        $changes = [];
        foreach ($fieldMap as $plrField => $_) {
            $oldValue = PlrFileWriter::readField($line, $plrField);
            if ($oldValue !== 0) {
                $changes[$plrField] = 0;
            }
        }
        return $changes;
    }

    /**
     * Derive seasonGamesStarted heuristically since box scores carry no starter signal.
     *
     * Four branches in priority order:
     *   1. Pure bench (base.gs == 0): new.gs = 0 (returned as no-op — base already has 0)
     *   2. Pure starter (base.gs == base.gp && base.gp > 0): new.gs = new.gp
     *   3. Bad base data (base.gp <= 0 && base.gs > 0): zero out, the heuristic can't pro-rate
     *   4. Mixed roster role (default): pro-rate by base's starter rate, rounded to nearest
     *
     * @param array<string, int> $priorChanges
     * @return array<string, int>
     */
    private function buildGamesStartedChangeSet(string $line, array $priorChanges): array
    {
        $baseGs = PlrFileWriter::readField($line, 'seasonGamesStarted');
        $baseGp = PlrFileWriter::readField($line, 'seasonGamesPlayed');
        $newGp = $priorChanges['seasonGamesPlayed'] ?? $baseGp;

        if ($baseGs === 0) {
            return [];
        }
        if ($baseGp > 0 && $baseGs === $baseGp) {
            return $baseGs !== $newGp ? ['seasonGamesStarted' => $newGp] : [];
        }
        if ($baseGp <= 0) {
            return ['seasonGamesStarted' => 0];
        }
        $newGs = (int) round($newGp * ($baseGs / $baseGp));
        return $newGs !== $baseGs ? ['seasonGamesStarted' => $newGs] : [];
    }

    /**
     * Build change set for season-highs fields + their career-best counterparts.
     *
     * @param array{high_pts: int, high_reb: int, high_ast: int, high_stl: int, high_blk: int, doubles: int, triples: int} $highs
     * @param array<string, array{0: string, 1: string}> $fieldMap
     * @return array<string, int>
     */
    private function buildHighsChangeSet(string $line, array $highs, array $fieldMap): array
    {
        $changes = [];
        foreach ($fieldMap as $seasonHighField => [$highsKey, $careerBestField]) {
            /** @var int $newHigh */
            $newHigh = $highs[$highsKey] ?? 0;

            $oldSeasonHigh = PlrFileWriter::readField($line, $seasonHighField);
            if ($oldSeasonHigh !== $newHigh) {
                $changes[$seasonHighField] = $newHigh;
            }

            $baseCareerBest = PlrFileWriter::readField($line, $careerBestField);
            $newCareerBest = max($baseCareerBest, $newHigh);
            if ($newCareerBest !== $baseCareerBest) {
                $changes[$careerBestField] = $newCareerBest;
            }
        }
        return $changes;
    }

    /**
     * Zero out single-season highs when no box scores exist for the window.
     *
     * Career bests are preserved — they're monotonic across seasons and should not decrease
     * just because the current season has no playoff games yet.
     *
     * @param array<string, array{0: string, 1: string}> $fieldMap
     * @return array<string, int>
     */
    private function buildZeroedHighsChangeSet(string $line, array $fieldMap): array
    {
        $changes = [];
        foreach ($fieldMap as $seasonHighField => [$_, $__]) {
            $oldValue = PlrFileWriter::readField($line, $seasonHighField);
            if ($oldValue !== 0) {
                $changes[$seasonHighField] = 0;
            }
        }
        return $changes;
    }
}
