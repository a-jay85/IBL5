<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\PlrRatingsCongruenceCheckStep;

/**
 * @covers \Updater\Steps\PlrRatingsCongruenceCheckStep
 */
class PlrRatingsCongruenceCheckStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $this->assertTrue(
            (new \ReflectionClass(PlrRatingsCongruenceCheckStep::class))
                ->implementsInterface(PipelineStepInterface::class)
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');

        $this->assertSame('PLR ratings congruence check', $step->getLabel());
    }

    /**
     * @param array{phase: string, reason: string} $case
     */
    #[DataProvider('outsideCheckedPhasesProvider')]
    public function testSkipsOutsideCheckedPhases(array $case): void
    {
        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mock = self::createMock(JsbSourceResolverInterface::class);
        $mock->expects($this->never())->method('getContents');

        $step = new PlrRatingsCongruenceCheckStep($mock, $case['phase']);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame($case['reason'], $result->detail);
    }

    /**
     * @return array<string, array{array{phase: string, reason: string}}>
     */
    public static function outsideCheckedPhasesProvider(): array
    {
        return [
            'Draft'        => [['phase' => 'Draft', 'reason' => 'Not checked during Draft phase']],
            'Free Agency'  => [['phase' => 'Free Agency', 'reason' => 'Not checked during Free Agency phase']],
            'unset'        => [['phase' => '', 'reason' => 'Not checked during an unset phase']],
        ];
    }

    public function testSkipsWhenPlrFileMissing(): void
    {
        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn(null);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('PLR file not found', $result->detail);
    }

    public function testPreseasonMismatchUsesPendingResetMessage(): void
    {
        // Nash 2007: FTM/FTA 232/528, FTP rating 94. Expected = round(100*232/528) = 44. Drift +50.
        $line = $this->buildPlrLine(3558, 'Steve Nash', 0, 0, 232, 528, 0, 0, 0, 94, 0);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Preseason');
        $result = $step->execute();

        $this->assertSame(
            ['ERROR: Steve Nash (pid 3558): FTP rating 94 (expected 44 from real-life 232/528). This rating will be overwritten by the start of the Regular Season unless the real-life line is updated to match.'],
            $result->messages,
        );
        $this->assertSame(1, $result->messageErrorCount);
    }

    public function testHeatMismatchUsesPendingResetMessage(): void
    {
        // Delfino 2006: FGM/FGA 223/463, 3GM/3GA 85/213. 2GP: 138/250=55. Rating 5. Drift -50.
        $line = $this->buildPlrLine(5295, 'Carlos Delfino', 223, 463, 86, 118, 85, 213, 5, 73, 40);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'HEAT');
        $result = $step->execute();

        $this->assertSame(
            ['ERROR: Carlos Delfino (pid 5295): 2GP rating 5 (expected 55 from real-life 138/250). This rating will be overwritten by the start of the Regular Season unless the real-life line is updated to match.'],
            $result->messages,
        );
        $this->assertSame(1, $result->messageErrorCount);
    }

    public function testRegularSeasonMismatchUsesSettledMessage(): void
    {
        // Nash 2008: FTM/FTA 211/251. Expected = round(100*211/251) = 84. Rating 45. Drift -39.
        $line = $this->buildPlrLine(3558, 'Steve Nash', 790, 1469, 211, 251, 192, 438, 58, 45, 44);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame(
            ['ERROR: Steve Nash (pid 3558): FTP rating 45 disagrees with real-life stat line (expected 84 from real-life 211/251).'],
            $result->messages,
        );
    }

    public function testPlayoffsMismatchUsesSettledMessage(): void
    {
        // Cousins 2009: 3GM/3GA 73/215. Expected = round(100*73/215) = 34. Rating 0. Drift -34.
        $line = $this->buildPlrLine(6884, 'DeMarcus Cousins', 612, 1292, 393, 538, 73, 215, 50, 73, 0);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Playoffs');
        $result = $step->execute();

        $this->assertSame(
            ['ERROR: DeMarcus Cousins (pid 6884): 3GP rating 0 disagrees with real-life stat line (expected 34 from real-life 73/215).'],
            $result->messages,
        );
    }

    public function testDriftBelowThresholdDoesNotFlag(): void
    {
        // FT 80/100 = 80. Ratings 82 and 78: |±2| < DRIFT_THRESHOLD(3).
        $min = PlrRatingsCongruenceCheckStep::MIN_ATTEMPTS;
        $line1 = $this->buildPlrLine(1001, 'Player A', 0, 0, $min * 4, $min * 5, 0, 0, 0, 82, 0);
        $line2 = $this->buildPlrLine(1002, 'Player B', 0, 0, $min * 4, $min * 5, 0, 0, 0, 78, 0);
        $data = implode("\r\n", [$line1, $line2]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
        $this->assertStringStartsWith('All shooting ratings match real-life stats (2 players checked)', $result->detail);
    }

    public function testDriftAtThresholdFlagsInBothDirections(): void
    {
        // FT 80/100 = 80. Ratings 83 (+3) and 77 (-3): both at DRIFT_THRESHOLD.
        $min = PlrRatingsCongruenceCheckStep::MIN_ATTEMPTS;
        $line1 = $this->buildPlrLine(1001, 'Player A', 0, 0, $min * 4, $min * 5, 0, 0, 0, 83, 0);
        $line2 = $this->buildPlrLine(1002, 'Player B', 0, 0, $min * 4, $min * 5, 0, 0, 0, 77, 0);
        $data = implode("\r\n", [$line1, $line2]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame(2, $result->messageErrorCount);
    }

    public function testBelowMinimumAttemptsIsNotChecked(): void
    {
        // FTA = MIN_ATTEMPTS - 1 = 19, FTP 0. Below cutoff; should not flag.
        $below = PlrRatingsCongruenceCheckStep::MIN_ATTEMPTS - 1;
        $line = $this->buildPlrLine(1001, 'Player A', 0, 0, $below, $below, 0, 0, 0, 0, 0);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame([], $result->messages);
    }

    public function testAtMinimumAttemptsIsChecked(): void
    {
        // FTA = MIN_ATTEMPTS = 20, FTM 20, FTP 0. Expected 100, drift -100 >= 3. Flags.
        $min = PlrRatingsCongruenceCheckStep::MIN_ATTEMPTS;
        $line = $this->buildPlrLine(1001, 'Player A', 0, 0, $min, $min, 0, 0, 0, 0, 0);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame(1, $result->messageErrorCount);
    }

    public function testZeroAttemptsDoesNotThrow(): void
    {
        // All real-life stats 0, all ratings 50. Attempts 0 < MIN_ATTEMPTS; no division occurs.
        $line = $this->buildPlrLine(1001, 'Player A', 0, 0, 0, 0, 0, 0, 50, 50, 50);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->messages);
    }

    public function testTwoPointCheckExcludesThreePointAttempts(): void
    {
        // FGM/FGA 500/1000, 3GM/3GA 100/300. 2GP: 400/700=57. Total FG%: 500/1000=50.
        // Player 1 rated 2GP 50 (disagrees with 57); Player 2 rated 2GP 57 (matches).
        // Both have 3GP 33 (matches round(100*100/300)=33).
        $line1 = $this->buildPlrLine(1001, 'Player A', 500, 1000, 0, 0, 100, 300, 50, 0, 33);
        $line2 = $this->buildPlrLine(1002, 'Player B', 500, 1000, 0, 0, 100, 300, 57, 0, 33);
        $data = implode("\r\n", [$line1, $line2]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame(1, $result->messageErrorCount);
        $this->assertStringContainsString('2GP rating 50', $result->messages[0]);
        $this->assertStringContainsString('expected 57 from real-life 400/700', $result->messages[0]);
    }

    public function testRoundsHalfAwayFromZero(): void
    {
        // 3GM/3GA 3/40 = 7.5. PHP round() → 8. Rating 3GP 5. Drift = 5 - 8 = -3 → flags.
        $min = PlrRatingsCongruenceCheckStep::MIN_ATTEMPTS;
        $line = $this->buildPlrLine(1001, 'Player A', 0, 0, 0, 0, 3, $min * 2, 0, 0, 5);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame(1, $result->messageErrorCount);
        $this->assertStringContainsString('expected 8', $result->messages[0]);
    }

    public function testSkipsTeamAndBlankRows(): void
    {
        // Two player lines, one pid-0 line, and a trailing empty string. Only 2 players checked.
        $line1 = $this->buildPlrLine(1001, 'Player A', 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $line2 = $this->buildPlrLine(1002, 'Player B', 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $teamRow = $this->buildPlrLine(0, 'TEAM TOTALS', 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $data = implode("\r\n", [$line1, $line2, $teamRow, '']);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertStringContainsString('(2 players checked)', $result->detail);
    }

    public function testCapsListedMessagesAndKeepsUncappedCount(): void
    {
        // 30 players, each FT 80/100 with FTP 50. Expected 80, drift -30. All flag.
        $max = PlrRatingsCongruenceCheckStep::MAX_LISTED;
        $lines = [];
        for ($i = 1; $i <= 30; $i++) {
            $lines[] = $this->buildPlrLine($i, sprintf('Player %02d', $i), 0, 0, 80, 100, 0, 0, 0, 50, 0);
        }
        $data = implode("\r\n", $lines);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertCount($max + 1, $result->messages);
        $this->assertSame('ERROR: ...and 5 more rating mismatches not listed', $result->messages[$max]);
        $this->assertSame(30, $result->messageErrorCount);
        foreach (array_slice($result->messages, 0, $max) as $msg) {
            $this->assertStringStartsWith('ERROR: ', $msg);
        }
    }

    public function testExactlyMaxListedMismatchesHasNoSummaryLine(): void
    {
        // 25 players — exactly MAX_LISTED. No "...and N more" line.
        $max = PlrRatingsCongruenceCheckStep::MAX_LISTED;
        $lines = [];
        for ($i = 1; $i <= $max; $i++) {
            $lines[] = $this->buildPlrLine($i, sprintf('Player %02d', $i), 0, 0, 80, 100, 0, 0, 0, 50, 0);
        }
        $data = implode("\r\n", $lines);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertCount($max, $result->messages);
        foreach ($result->messages as $msg) {
            $this->assertStringNotContainsString('more rating mismatches', $msg);
        }
        $this->assertSame($max, $result->messageErrorCount);
    }

    public function testMultipleMismatchesOnOnePlayerAreListedInCheckOrder(): void
    {
        // One player off on all three: 2GP 400/700=57 rated 20, FT 80/100=80 rated 20, 3GP 100/300=33 rated 0.
        $line = $this->buildPlrLine(1001, 'Player A', 500, 1000, 80, 100, 100, 300, 20, 20, 0);
        $data = $line;

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $stub = self::createStub(JsbSourceResolverInterface::class);
        $stub->method('getContents')->willReturn($data);

        $step = new PlrRatingsCongruenceCheckStep($stub, 'Regular Season');
        $result = $step->execute();

        $this->assertSame(3, $result->messageErrorCount);
        $this->assertStringContainsString('2GP', $result->messages[0]);
        $this->assertStringContainsString('FTP', $result->messages[1]);
        $this->assertStringContainsString('3GP', $result->messages[2]);
        $this->assertStringContainsString('3 rating mismatch(es) across 1 player(s)', $result->detail);
    }

    private function buildPlrLine(
        int $pid,
        string $name,
        int $fgm,
        int $fga,
        int $ftm,
        int $fta,
        int $tgm,
        int $tga,
        int $r2gp,
        int $rftp,
        int $r3gp,
    ): string {
        $line = str_repeat(' ', 700);
        $line = substr_replace($line, str_pad('1', 4, ' ', STR_PAD_LEFT), 0, 4);
        $line = substr_replace($line, str_pad($name, 32, ' ', STR_PAD_RIGHT), 4, 32);
        $line = substr_replace($line, str_pad((string) $pid, 6, ' ', STR_PAD_LEFT), 38, 6);
        $line = substr_replace($line, str_pad((string) $fgm, 4, ' ', STR_PAD_LEFT), 60, 4);
        $line = substr_replace($line, str_pad((string) $fga, 4, ' ', STR_PAD_LEFT), 64, 4);
        $line = substr_replace($line, str_pad((string) $ftm, 4, ' ', STR_PAD_LEFT), 68, 4);
        $line = substr_replace($line, str_pad((string) $fta, 4, ' ', STR_PAD_LEFT), 72, 4);
        $line = substr_replace($line, str_pad((string) $tgm, 4, ' ', STR_PAD_LEFT), 76, 4);
        $line = substr_replace($line, str_pad((string) $tga, 4, ' ', STR_PAD_LEFT), 80, 4);
        $line = substr_replace($line, str_pad((string) $r2gp, 3, ' ', STR_PAD_LEFT), 558, 3);
        $line = substr_replace($line, str_pad((string) $rftp, 3, ' ', STR_PAD_LEFT), 564, 3);
        $line = substr_replace($line, str_pad((string) $r3gp, 3, ' ', STR_PAD_LEFT), 570, 3);
        return $line;
    }
}
