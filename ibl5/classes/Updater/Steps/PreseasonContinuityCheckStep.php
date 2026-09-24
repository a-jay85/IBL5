<?php

declare(strict_types=1);

namespace Updater\Steps;

use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\PlrLineParser;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Advisory check that the current .plr file matches the last Preseason snapshot.
 *
 * Runs during HEAT and Regular Season only. If a Preseason snapshot exists and
 * fields in the current .plr differ, it logs per-field error lines so the GM can
 * re-apply any lost Preseason .plr edits before the season is too far underway.
 *
 * Field sets: HEAT compares 30 fields (ALWAYS_COMPARED + HEAT_ONLY); Regular Season
 * compares 24 (ALWAYS_COMPARED only, because JSB rebuilds the six stat ratings at the
 * Regular Season start). Excluded fields: r_fgp, r_ftp, r_3gp (JSB rebuilds them),
 * r_fga, r_fta, r_3ga (volume, out of scope), and r_foul (derived from rl_pf/rl_min,
 * so it cascades a flag when the league-max player's line moves).
 *
 * IBL-only — Olympics league does not use this step.
 */
final class PreseasonContinuityCheckStep implements PipelineStepInterface
{
    private const CHECKED_PHASES = ['HEAT', 'Regular Season'];
    private const MAX_LISTED = 25;

    /** @var array<string, string> snapshot column => PlrLineParser key */
    private const ALWAYS_COMPARED = [
        'pos' => 'pos',
        'rl_gp' => 'realLifeGP',
        'rl_min' => 'realLifeMIN',
        'rl_fgm' => 'realLifeFGM',
        'rl_fga' => 'realLifeFGA',
        'rl_ftm' => 'realLifeFTM',
        'rl_fta' => 'realLifeFTA',
        'rl_3gm' => 'realLife3GM',
        'rl_3ga' => 'realLife3GA',
        'rl_orb' => 'realLifeORB',
        'rl_drb' => 'realLifeDRB',
        'rl_ast' => 'realLifeAST',
        'rl_stl' => 'realLifeSTL',
        'rl_tvr' => 'realLifeTVR',
        'rl_blk' => 'realLifeBLK',
        'rl_pf' => 'realLifePF',
        'oo' => 'ratingOO',
        'od' => 'ratingOD',
        'r_drive_off' => 'ratingDO',
        'dd' => 'ratingDD',
        'po' => 'ratingPO',
        'pd' => 'ratingPD',
        'r_trans_off' => 'ratingTO',
        'td' => 'ratingTD',
    ];

    /** @var array<string, string> */
    private const HEAT_ONLY = [
        'r_orb' => 'ratingORB',
        'r_drb' => 'ratingDRB',
        'r_ast' => 'ratingAST',
        'r_stl' => 'ratingSTL',
        'r_tvr' => 'ratingTVR',
        'r_blk' => 'ratingBLK',
    ];

    public function __construct(
        private readonly PlrParserRepositoryInterface $plrRepo,
        private readonly JsbSourceResolverInterface $sourceResolver,
        private readonly int $seasonEndingYear,
        private readonly string $seasonPhase,
    ) {
    }

    public function getLabel(): string
    {
        return 'Preseason continuity check';
    }

    public function execute(): StepResult
    {
        if (!in_array($this->seasonPhase, self::CHECKED_PHASES, true)) {
            return StepResult::skipped($this->getLabel(), 'Not HEAT or Regular Season phase');
        }
        $preseason = $this->plrRepo->getSnapshotsByPhase($this->seasonEndingYear, 'preseason');
        if ($preseason === []) {
            return StepResult::skipped($this->getLabel(), 'No preseason snapshot for ' . $this->seasonEndingYear);
        }
        $data = $this->sourceResolver->getContents('plr');
        if ($data === null) {
            return StepResult::skipped($this->getLabel(), 'PLR file not found');
        }

        $fields = $this->seasonPhase === 'HEAT'
            ? self::ALWAYS_COMPARED + self::HEAT_ONLY
            : self::ALWAYS_COMPARED;

        $errors = [];
        $compared = 0;
        foreach (explode("\r\n", $data) as $line) {
            $current = PlrLineParser::parse($line);
            if ($current === null) {
                continue;
            }
            $pid = (int) $current['pid'];
            if (!isset($preseason[$pid])) {
                continue;
            }
            $compared++;
            foreach ($fields as $column => $key) {
                $before = self::normalize($preseason[$pid][$column] ?? null);
                $after = self::normalize($current[$key] ?? null);
                if ($before !== $after) {
                    $errors[] = sprintf(
                        'ERROR: %s (pid %d): %s preseason=%s current=%s',
                        (string) $current['name'], $pid, $column, $before, $after,
                    );
                }
            }
        }

        $total = count($errors);
        if ($total === 0) {
            return StepResult::success($this->getLabel(), sprintf('%d players match their Preseason snapshot', $compared));
        }
        $messages = array_slice($errors, 0, self::MAX_LISTED);
        if ($total > self::MAX_LISTED) {
            $messages[] = sprintf('...and %d more', $total - self::MAX_LISTED);
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d field(s) differ from the Preseason snapshot; re-apply any lost Preseason .plr edits', $total),
            messages: $messages,
            messageErrorCount: $total,
        );
    }

    private static function normalize(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
