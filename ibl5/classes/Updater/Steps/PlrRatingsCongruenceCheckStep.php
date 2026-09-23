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

            $name = (string) $p['name'];
            $pid = (int) $p['pid'];
            $fgm = (int) $p['realLifeFGM'];
            $fga = (int) $p['realLifeFGA'];
            $ftm = (int) $p['realLifeFTM'];
            $fta = (int) $p['realLifeFTA'];
            $tgm = (int) $p['realLife3GM'];
            $tga = (int) $p['realLife3GA'];
            $r2gp = (int) $p['rating2GP'];
            $rftp = (int) $p['ratingFTP'];
            $r3gp = (int) $p['rating3GP'];

            /** @var list<string> $playerMismatches */
            $playerMismatches = [];

            $made2gp = $fgm - $tgm;
            $att2gp = $fga - $tga;
            $drift2gp = self::drift($made2gp, $att2gp, $r2gp);
            if ($drift2gp !== null) {
                $playerMismatches[] = $this->buildMessage(
                    $mode, $name, $pid, '2GP',
                    $r2gp, $drift2gp['expected'], $made2gp, $att2gp,
                );
            }

            $driftFtp = self::drift($ftm, $fta, $rftp);
            if ($driftFtp !== null) {
                $playerMismatches[] = $this->buildMessage(
                    $mode, $name, $pid, 'FTP',
                    $rftp, $driftFtp['expected'], $ftm, $fta,
                );
            }

            $drift3gp = self::drift($tgm, $tga, $r3gp);
            if ($drift3gp !== null) {
                $playerMismatches[] = $this->buildMessage(
                    $mode, $name, $pid, '3GP',
                    $r3gp, $drift3gp['expected'], $tgm, $tga,
                );
            }

            if (count($playerMismatches) > 0) {
                $playersFlagged++;
                foreach ($playerMismatches as $msg) {
                    $all[] = $msg;
                }
            }
        }

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
