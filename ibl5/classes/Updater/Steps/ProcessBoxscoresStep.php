<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use Boxscore\RejectSummary;
use Discord\Discord;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\SourceProvenance;
use Updater\StepResult;

/**
 * Step 8: Process boxscores from .sco file.
 *
 * Reads .sco data via the archive-first resolver. Skips if no .sco source is available.
 */
class ProcessBoxscoresStep implements PipelineStepInterface
{
    private const DISCORD_CHANNEL = '#admin-chat';

    /**
     * Posted to Discord (and prepended to operator messages) when the schedule guard
     * ran in fail-open mode because ibl_schedule has no rows for the operating season.
     */
    private const GUARD_DISABLED_NOTICE = 'WARNING: the boxscore schedule guard was DISABLED for this run — ibl_schedule has no rows for the operating season, so every game was imported without a membership check. Import the schedule for this season, then re-run bin/check-boxscore-schedule.';

    public function __construct(
        private readonly BoxscoreProcessor $processor,
        private readonly BoxscoreView $view,
        private readonly JsbSourceResolverInterface $sourceResolver,
    ) {
    }

    public function getLabel(): string
    {
        return 'Boxscores processed';
    }

    public function execute(): StepResult
    {
        $data = $this->sourceResolver->getContents('sco');
        if ($data === null) {
            return StepResult::skipped('Boxscores', 'No IBL5.sco file found (skipped)');
        }

        $provenance = $this->sourceResolver->describeLastSource();
        $scoResult = $this->processor->processScoData($data, 0, '', sourceArchive: $provenance?->name);
        $scoResult['sourceArchive'] = $scoResult['sourceArchive'] ?? $provenance?->name;

        $inlineHtml = $this->view->renderParseLog($scoResult);

        $rejectSummary = RejectSummary::fromRejects(
            $scoResult['rejectedGames'] ?? [],
            $scoResult['sourceArchive'] ?? null,
            $scoResult['rejectsRecorded'] ?? null,
        );

        $guardDisabled = ($scoResult['scheduleGuardEnabled'] ?? true) === false
            && ($scoResult['linesProcessed'] ?? 0) > 0;

        // Only operator signals that the "Parse Results" card does NOT already show.
        // The processor's own $scoResult['messages'], the reject headline/triples and
        // the reject count are all rendered inside that card, so repeating them here
        // was pure duplication.
        /** @var list<string> $messages */
        $messages = [];
        if ($guardDisabled) {
            $messages[] = self::GUARD_DISABLED_NOTICE;
        }

        $messages = [...$messages, ...$this->buildProvenanceWarnings($provenance, $scoResult)];
        $messages = [...$messages, ...$this->buildRejectMessages($rejectSummary)];
        $messages = [...$messages, ...$this->dispatchNotifications($rejectSummary, $guardDisabled)];

        // The reject headline is the one top-level fact an operator must not have to
        // expand a <details> to see; renderStepComplete prints $detail inline.
        $detail = $rejectSummary->isEmpty() ? '' : $rejectSummary->headline();

        return StepResult::success(
            $this->getLabel(),
            $detail,
            collapsibleLog: true,
            inlineHtml: $inlineHtml,
            messages: $messages,
            messageErrorCount: 0,
        );
    }

    /**
     * Build provenance warnings (null source / disk fallback / mis-named / season mismatch).
     * These are operator-facing only — never appended to the Discord reject message
     * because the 1 900-char cap is already consumed by reject lines.
     *
     * @param array<string, mixed> $scoResult
     * @return list<string>
     */
    private function buildProvenanceWarnings(?SourceProvenance $provenance, array $scoResult): array
    {
        /** @var list<string> $warnings */
        $warnings = [];

        if ($provenance === null) {
            $warnings[] = 'WARNING: JSB source provenance unavailable; archive-vs-season check skipped.';
        } elseif ($provenance->kind === SourceProvenance::KIND_DISK) {
            $warnings[] = 'WARNING: Boxscores read from disk file ' . $provenance->name . ' (no archive found). Season-match check skipped.';
        } elseif (!$provenance->isProperlyNamed()) {
            $warnings[] = 'WARNING: Active archive ' . $provenance->name . ' is not properly named; season-match check skipped.';
        } elseif ($provenance->declaredSeasonEndingYear !== null) {
            $archiveYear   = $provenance->declaredSeasonEndingYear;
            $rawYear       = $scoResult['operatingSeasonEndingYear'] ?? null;
            $operatingYear = is_int($rawYear) ? $rawYear : null;
            if ($operatingYear !== null && $archiveYear !== $operatingYear) {
                $warnings[] = sprintf(
                    'WARNING: Archive %s declares season ending %d but operating season is %d. Wrong archive?',
                    $provenance->name,
                    $archiveYear,
                    $operatingYear,
                );
            }
        }

        // §8.9.6: surface archive-selection warnings from the ranking step.
        if ($provenance !== null) {
            foreach ($provenance->selectionWarnings as $selectionWarning) {
                $warnings[] = 'WARNING (archive selection): ' . $selectionWarning;
            }
        }

        return $warnings;
    }

    /**
     * Build reject lines the "Parse Results" card cannot show.
     *
     * The card builds its own RejectSummary without $rejectsRecorded, so the audit
     * note ("rejects were not written to the audit table") has no other surface.
     * The headline / per-game triples / overflow line ARE on the card — emitting
     * them here too was the duplication this method used to create.
     *
     * @return list<string>
     */
    private function buildRejectMessages(RejectSummary $rejectSummary): array
    {
        if ($rejectSummary->isEmpty()) {
            return [];
        }

        $auditNote = $rejectSummary->auditNote();

        return $auditNote !== '' ? [$auditNote] : [];
    }

    /**
     * Post Discord notifications for rejects and/or a disabled guard.
     * Each notification is best-effort: failures are captured as operator messages.
     * Returns a list of error messages (empty when both succeed).
     *
     * @return list<string>
     */
    private function dispatchNotifications(RejectSummary $rejectSummary, bool $guardDisabled): array
    {
        /** @var list<string> $errorMessages */
        $errorMessages = [];

        if (!$rejectSummary->isEmpty()) {
            try {
                $this->postToDiscord(self::DISCORD_CHANNEL, $rejectSummary->discordMessage());
            } catch (\Throwable $e) {
                $errorMessages[] = 'Discord reject notification failed: ' . $e->getMessage();
            }
        }

        if ($guardDisabled) {
            try {
                $this->postToDiscord(self::DISCORD_CHANNEL, self::GUARD_DISABLED_NOTICE);
            } catch (\Throwable $e) {
                $errorMessages[] = 'Discord guard-disabled notification failed: ' . $e->getMessage();
            }
        }

        return $errorMessages;
    }

    /**
     * Seam for tests. Production posts to the #admin-chat webhook; the real
     * Discord class is a no-op under PHPUnit, so tests override this to observe
     * the dispatched message.
     */
    protected function postToDiscord(string $channel, string $message): void
    {
        Discord::postToChannel($channel, $message);
    }
}
