<?php

declare(strict_types=1);

namespace Updater\Steps;

use PlrParser\PlrLineParser;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * IBL-only. Report-only; never edits the .plr file.
 *
 * Corpus evidence shows every Preseason and HEAT mismatch vanishes at season start,
 * so the mode map puts both phases in pending-reset; Regular Season and Playoffs
 * mismatches persist, so they are settled. Volume ratings (2GA, FTA, 3GA) are
 * unchecked because no fixed formula reproduces them from real-life stats.
 */
final class PlrRatingsCongruenceCheckStep implements PipelineStepInterface
{
    public function __construct(
        private readonly JsbSourceResolverInterface $sourceResolver,
        private readonly string $seasonPhase,
    ) {
    }

    public const MODE_PENDING_RESET = 'pending-reset';
    public const MODE_SETTLED = 'settled';

    /** Setting value => mode. Anything absent is skipped (Draft/Free Agency flood, per corpus). */
    private const MODE_BY_PHASE = [
        'Preseason' => self::MODE_PENDING_RESET,
        'HEAT' => self::MODE_PENDING_RESET,
        'Regular Season' => self::MODE_SETTLED,
        'Playoffs' => self::MODE_SETTLED,
    ];

    public const MIN_ATTEMPTS = 20;
    public const DRIFT_THRESHOLD = 3;
    public const MAX_LISTED = 25;

    public function getLabel(): string
    {
        return 'PLR ratings congruence check';
    }

    public function execute(): StepResult
    {
        $mode = self::MODE_BY_PHASE[$this->seasonPhase] ?? null;
        if ($mode === null) {
            $phaseLabel = $this->seasonPhase === '' ? 'an unset' : $this->seasonPhase;
            return StepResult::skipped($this->getLabel(), sprintf('Not checked during %s phase', $phaseLabel));
        }

        $data = $this->sourceResolver->getContents('plr');
        if ($data === null) {
            return StepResult::skipped($this->getLabel(), 'PLR file not found');
        }

        /** @var list<string> $all */
        $all = [];
        $checked = 0;
        $playersFlagged = 0;

        foreach (explode("\r\n", $data) as $line) {
            $p = PlrLineParser::parse($line);
            if ($p === null) {
                continue;
            }
            $checked++;

            $playerMismatches = $this->mismatchesForPlayer($mode, $p);

            if (count($playerMismatches) > 0) {
                $playersFlagged++;
                foreach ($playerMismatches as $msg) {
                    $all[] = $msg;
                }
            }
        }

        return $this->buildResult($mode, $checked, $playersFlagged, $all);
    }

    /**
     * @param list<string> $all
     */
    private function buildResult(string $mode, int $checked, int $playersFlagged, array $all): StepResult
    {
        $total = count($all);

        if ($total === 0) {
            return StepResult::success(
                $this->getLabel(),
                sprintf('All shooting ratings match real-life stats (%d players checked) [%s]', $checked, $mode),
            );
        }

        if ($total > self::MAX_LISTED) {
            /** @var list<string> $messages */
            $messages = array_slice($all, 0, self::MAX_LISTED);
            $messages[] = sprintf('ERROR: ...and %d more rating mismatches not listed', $total - self::MAX_LISTED);
        } else {
            $messages = $all;
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d rating mismatch(es) across %d player(s) [%s]', $total, $playersFlagged, $mode),
            messages: $messages,
            messageErrorCount: $total,
        );
    }

    /**
     * Runs the three shooting checks for one parsed player in 2GP, FTP, 3GP order.
     * The 2GP row subtracts threes from field goals, so 3-point attempts never count toward 2GP.
     *
     * @param array<string, int|string> $p
     * @return list<string>
     */
    private function mismatchesForPlayer(string $mode, array $p): array
    {
        /** @infection-ignore-all Equivalent: PlrLineParser builds name from trim() and toUtf8(), so it is already a string and dropping this cast cannot change it. */
        $name = (string) $p['name'];
        $pid = self::intField($p, 'pid');
        $fgm = self::intField($p, 'realLifeFGM');
        $fga = self::intField($p, 'realLifeFGA');
        $ftm = self::intField($p, 'realLifeFTM');
        $fta = self::intField($p, 'realLifeFTA');
        $tgm = self::intField($p, 'realLife3GM');
        $tga = self::intField($p, 'realLife3GA');

        $checks = [
            ['2GP', $fgm - $tgm, $fga - $tga, self::intField($p, 'rating2GP')],
            ['FTP', $ftm, $fta, self::intField($p, 'ratingFTP')],
            ['3GP', $tgm, $tga, self::intField($p, 'rating3GP')],
        ];

        $mismatches = [];
        foreach ($checks as [$label, $made, $attempts, $rating]) {
            $drift = self::drift($made, $attempts, $rating);
            if ($drift !== null) {
                $mismatches[] = $this->buildMessage(
                    $mode, $name, $pid, $label,
                    $rating, $drift['expected'], $made, $attempts,
                );
            }
        }

        return $mismatches;
    }

    /**
     * @param array<string, int|string> $p
     */
    private static function intField(array $p, string $key): int
    {
        /** @infection-ignore-all Equivalent: PlrLineParser casts pid and every realLife and rating field to int, so dropping this cast cannot change the value. */
        return (int) $p[$key];
    }

    /**
     * @return array{expected: int, drift: int}|null
     */
    private static function drift(int $made, int $attempts, int $rating): ?array
    {
        if ($attempts < self::MIN_ATTEMPTS) {
            return null;
        }
        $expected = (int) round(100 * $made / $attempts);
        $drift = $rating - $expected;
        return abs($drift) >= self::DRIFT_THRESHOLD ? ['expected' => $expected, 'drift' => $drift] : null;
    }

    private function buildMessage(
        string $mode,
        string $name,
        int $pid,
        string $label,
        int $rating,
        int $expected,
        int $made,
        int $attempts,
    ): string {
        if ($mode === self::MODE_PENDING_RESET) {
            return sprintf(
                'ERROR: %s (pid %d): %s rating %d (expected %d from real-life %d/%d). '
                    . 'This rating will be overwritten by the start of the Regular Season unless the real-life line is updated to match.',
                $name, $pid, $label, $rating, $expected, $made, $attempts,
            );
        }

        return sprintf(
            'ERROR: %s (pid %d): %s rating %d disagrees with real-life stat line (expected %d from real-life %d/%d).',
            $name, $pid, $label, $rating, $expected, $made, $attempts,
        );
    }
}
