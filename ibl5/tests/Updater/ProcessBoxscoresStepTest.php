<?php

declare(strict_types=1);

namespace Tests\Updater;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use Boxscore\RejectedGame;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\SourceProvenance;
use Updater\Steps\ProcessBoxscoresStep;

class ProcessBoxscoresStepTest extends TestCase
{
    public function testGetLabelReturnsBoxscoresProcessed(): void
    {
        $step = $this->buildStep(scoContents: null);
        self::assertSame('Boxscores processed', $step->getLabel());
    }

    public function testSkipsWhenNoScoFileFound(): void
    {
        $step = $this->buildStep(scoContents: null);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsStringIgnoringCase('sco', $result->detail);
    }

    public function testSucceedsWhenScoFilePresent(): void
    {
        $scoData = 'IBL5.sco file data';

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 2,
            'gamesUpdated' => 1,
            'gamesSkipped' => 0,
            'linesProcessed' => 3,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($scoData);

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->collapsibleLog);
        self::assertStringContainsString('Parse log', $result->inlineHtml);
    }

    public function testPassesRejectCountAsMessageErrorCount(): void
    {
        $rejects = [
            new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: 1,
                homeTeamid: 2,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
            new RejectedGame(
                gameDate: '2008-04-06',
                visitorTeamid: 3,
                homeTeamid: 4,
                gameOfThatDay: 2,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
        ];

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 2,
            'linesProcessed' => 2,
            'messages' => [],
            'rejectedGames' => $rejects,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        // Reject headline / per-game triples / count all live on the "Parse Results"
        // card now; the step must not duplicate them into the operator message list.
        self::assertSame(0, $result->messageErrorCount);

        foreach ($result->messages as $msg) {
            self::assertStringStartsNotWith('2 game(s) rejected', $msg);
            self::assertStringStartsNotWith('  rejected:', $msg);
        }

        // ...but the headline must stay visible at the step line, without expanding
        // the collapsible log.
        self::assertStringStartsWith('2 game(s) rejected', $result->detail);
    }

    public function testStillSucceedsWhenGamesAreRejected(): void
    {
        $reject = new RejectedGame(
            gameDate: '2008-04-05',
            visitorTeamid: 1,
            homeTeamid: 2,
            gameOfThatDay: 1,
            reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
        );

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 1,
            'linesProcessed' => 1,
            'messages' => [],
            'rejectedGames' => [$reject],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
    }

    public function testMessageErrorCountIsZeroWhenNoRejects(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 2,
            'gamesUpdated' => 1,
            'gamesSkipped' => 0,
            'linesProcessed' => 3,
            'messages' => [],
            'operatingSeasonEndingYear' => 2026,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        // Provide a clean provenance so no provenance warnings are generated.
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_ARCHIVE,
                name: '25-26_10_reg-sim10.zip',
                declaredSeason: '25-26',
                declaredSeasonEndingYear: 2026,
                declaredPhase: 'reg-sim10',
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertSame(0, $result->messageErrorCount);
        self::assertSame([], $result->messages);
    }

    // ── Discord notification tests ─────────────────────────────────────────

    public function testPostsRejectNotificationToAdminChat(): void
    {
        $rejects = [
            new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: 21,
                homeTeamid: 17,
                gameOfThatDay: 4,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
            new RejectedGame(
                gameDate: '2008-06-25',
                visitorTeamid: 3,
                homeTeamid: 19,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
        ];

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 2,
            'linesProcessed' => 2,
            'messages' => [],
            'rejectedGames' => $rejects,
            'sourceArchive' => '07-08_36_playoffs.zip',
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $step->execute();

        self::assertCount(1, $step->posts, 'Exactly one Discord post should be made');
        self::assertSame('#admin-chat', $step->posts[0][0]);
        self::assertStringContainsString('2', $step->posts[0][1]);
        self::assertStringContainsString('21@17', $step->posts[0][1]);
        self::assertStringContainsString('3@19', $step->posts[0][1]);
    }

    public function testDoesNotPostWhenNoGamesRejected(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 3,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 3,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $step->execute();

        self::assertSame([], $step->posts, 'No Discord post on a clean run');
    }

    public function testDiscordFailureDoesNotFailTheStep(): void
    {
        $rejects = [
            new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: 1,
                homeTeamid: 2,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
            new RejectedGame(
                gameDate: '2008-04-06',
                visitorTeamid: 3,
                homeTeamid: 4,
                gameOfThatDay: 2,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
        ];

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 2,
            'linesProcessed' => 2,
            'messages' => [],
            'rejectedGames' => $rejects,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $step->throwOnPost = new \RuntimeException('Discord webhook failed with HTTP 400');
        $result = $step->execute();

        self::assertTrue($result->success, 'A Discord failure must not fail the step');
        self::assertSame(0, $result->messageErrorCount, 'Reject count is shown on the Parse Results card, not counted here');

        $hasFailureMessage = false;
        foreach ($result->messages as $msg) {
            if (str_starts_with($msg, 'Discord reject notification failed:')) {
                $hasFailureMessage = true;
                break;
            }
        }
        self::assertTrue($hasFailureMessage, 'Discord failure must appear as a message line');
    }

    public function testDiscordFailureStillRendersInlineHtml(): void
    {
        $rejects = [
            new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: 1,
                homeTeamid: 2,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
        ];

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 1,
            'linesProcessed' => 1,
            'messages' => [],
            'rejectedGames' => $rejects,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div class="rejects-block">Rejects here</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $step->throwOnPost = new \RuntimeException('Discord webhook failed with HTTP 400');
        $result = $step->execute();

        self::assertStringContainsString('rejects-block', $result->inlineHtml, 'inlineHtml must be populated even when Discord notification throws');
    }

    // ── Phase 8 provenance-hardening tests ────────────────────────────────────

    public function testWarnsOnSeasonMismatchBetweenArchiveAndOperatingSeason(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 5,
            'gamesSkipped' => 0,
            'linesProcessed' => 5,
            'messages' => [],
            'operatingSeasonEndingYear' => 2008,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        // Archive declares season 07-08 (ending 2007) but operating season ends 2008.
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_ARCHIVE,
                name: '07-07_36_playoffs.zip',
                declaredSeason: '06-07',
                declaredSeasonEndingYear: 2007,
                declaredPhase: 'playoffs',
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        $hasWarning = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'declares season ending 2007') && str_contains($msg, 'operating season is 2008')) {
                $hasWarning = true;
            }
        }
        self::assertTrue($hasWarning, 'Expected a season-mismatch warning in messages');
    }

    public function testNoProvenanceWarningWhenArchiveMatchesOperatingSeason(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 5,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 5,
            'messages' => [],
            'operatingSeasonEndingYear' => 2026,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_ARCHIVE,
                name: '25-26_10_reg-sim10.zip',
                declaredSeason: '25-26',
                declaredSeasonEndingYear: 2026,
                declaredPhase: 'reg-sim10',
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        $warnings = array_filter($result->messages, static fn (string $m): bool => str_contains($m, 'WARNING'));
        self::assertSame([], array_values($warnings), 'Expected no WARNING messages on a clean archive match');
    }

    public function testWarnsOnDiskFallbackSource(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_DISK,
                name: 'IBL5.sco',
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        $hasDiskWarning = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'disk file') && str_contains($msg, 'IBL5.sco')) {
                $hasDiskWarning = true;
            }
        }
        self::assertTrue($hasDiskWarning, 'Expected a disk-fallback warning in messages');
    }

    public function testWarnsOnMisnamedArchive(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_ARCHIVE,
                name: 'IBL2526Sim10.zip',
                // declaredSeasonEndingYear omitted → null → isProperlyNamed() = false
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        $hasWarning = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'not properly named') && str_contains($msg, 'IBL2526Sim10.zip')) {
                $hasWarning = true;
            }
        }
        self::assertTrue($hasWarning, 'Expected a misnamed-archive warning in messages');
    }

    public function testHandlesNullProvenanceWithoutFatal(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        // describeLastSource() returns null (default stub)
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);

        $hasWarning = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'JSB source provenance unavailable')) {
                $hasWarning = true;
            }
        }
        self::assertTrue($hasWarning, 'Expected a null-provenance warning in messages');
    }

    public function testSourceArchiveIsInjectedIntoResultBeforeRender(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'operatingSeasonEndingYear' => 2026,
        ]);

        /** @var \Boxscore\BoxscoreView&\PHPUnit\Framework\MockObject\Stub */
        $view = self::createStub(BoxscoreView::class);
        $capturedResult = null;
        $view->method('renderParseLog')
            ->willReturnCallback(static function (array $result) use (&$capturedResult): string {
                $capturedResult = $result;
                return '';
            });

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_ARCHIVE,
                name: '25-26_10_reg-sim10.zip',
                declaredSeason: '25-26',
                declaredSeasonEndingYear: 2026,
                declaredPhase: 'reg-sim10',
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $step->execute();

        self::assertIsArray($capturedResult);
        self::assertSame('25-26_10_reg-sim10.zip', $capturedResult['sourceArchive'] ?? '__missing__');
    }

    public function testSelectionWarningsAppearInStepMessages(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'operatingSeasonEndingYear' => 2008,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $selectionWarning = 'ARCHIVE MISFILED: 07-07_36_playoffs.zip declares season 06-07 but sits in the 07-08 backup directory.';
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');
        $resolver->method('describeLastSource')
            ->willReturn(new SourceProvenance(
                kind: SourceProvenance::KIND_ARCHIVE,
                name: '07-07_36_playoffs.zip',
                declaredSeason: '06-07',
                declaredSeasonEndingYear: 2007,
                declaredPhase: 'playoffs',
                selectionWarnings: [$selectionWarning],
            ));

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        $hasSelectionWarning = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'WARNING (archive selection):') && str_contains($msg, 'ARCHIVE MISFILED')) {
                $hasSelectionWarning = true;
            }
        }
        self::assertTrue($hasSelectionWarning, 'Expected archive-selection warning to appear in step messages');
    }

    // ── Phase 6.7 tests: guard-disabled path and legacy compat ────────────────

    public function testEmitsGuardDisabledNoticeWhenGuardIsOff(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'scheduleGuardEnabled' => false,
            'linesProcessed' => 5,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame(0, $result->messageErrorCount);

        $hasNotice = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'schedule guard was DISABLED')) {
                $hasNotice = true;
            }
        }
        self::assertTrue($hasNotice, 'Expected GUARD_DISABLED_NOTICE in messages');
    }

    public function testPostsGuardDisabledNoticeToDiscord(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'scheduleGuardEnabled' => false,
            'linesProcessed' => 3,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $step->execute();

        self::assertCount(1, $step->posts);
        self::assertStringContainsString('schedule guard was DISABLED', $step->posts[0][1]);
    }

    public function testDiscordGuardDisabledFailureDoesNotFailTheStep(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'scheduleGuardEnabled' => false,
            'linesProcessed' => 2,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $step->throwOnPost = new \RuntimeException('webhook down');
        $result = $step->execute();

        self::assertTrue($result->success);
        $hasError = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'Discord guard-disabled notification failed:')) {
                $hasError = true;
            }
        }
        self::assertTrue($hasError, 'Expected Discord guard-disabled failure message in operator messages');
    }

    public function testGuardDisabledNoticeNotEmittedWhenZeroLinesProcessed(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'scheduleGuardEnabled' => false,
            'linesProcessed' => 0,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        foreach ($result->messages as $msg) {
            self::assertStringNotContainsString('schedule guard was DISABLED', $msg);
        }
        self::assertCount(0, $step->posts, 'No Discord post when linesProcessed=0');
    }

    public function testLegacyResultWithoutScheduleGuardEnabledKeyStillWorks(): void
    {
        // Processor stubs that omit the new Phase 6.7 keys must not break the step.
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            // Deliberately omitting: scheduleGuardEnabled, rejectsRecorded, sourceArchive
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertCount(0, $step->posts, 'Legacy result must not trigger a Discord post');
    }

    public function testRejectsRecordedKeyIsPassedToRejectSummaryAuditNote(): void
    {
        $reject = new RejectedGame(
            gameDate: '2008-04-05',
            visitorTeamid: 1,
            homeTeamid: 2,
            gameOfThatDay: 1,
            reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
        );

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'messages' => [],
            'rejectedGames' => [$reject],
            'rejectsRecorded' => 0,  // Audit write failed
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $step = new SpyProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        $hasAuditNote = false;
        foreach ($result->messages as $msg) {
            if (str_contains($msg, 'AUDIT WRITE FAILED')) {
                $hasAuditNote = true;
            }
        }
        self::assertTrue($hasAuditNote, 'Expected AUDIT WRITE FAILED audit note in messages when rejectsRecorded=0');
    }

    private function buildStep(?string $scoContents): ProcessBoxscoresStep
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $view = self::createStub(BoxscoreView::class);

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($scoContents);

        return new ProcessBoxscoresStep($processor, $view, $resolver);
    }
}

final class SpyProcessBoxscoresStep extends ProcessBoxscoresStep
{
    /** @var list<array{0: string, 1: string}> */
    public array $posts = [];
    public ?\Throwable $throwOnPost = null;

    protected function postToDiscord(string $channel, string $message): void
    {
        $this->posts[] = [$channel, $message];
        if ($this->throwOnPost !== null) {
            throw $this->throwOnPost;
        }
    }
}
