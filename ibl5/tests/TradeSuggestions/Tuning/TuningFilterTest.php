<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Tuning\TuningFilter;
use TradeSuggestions\Tuning\TuningStatus;

final class TuningFilterTest extends TestCase
{
    public function testDefaultsToOneTeamAndAllStatuses(): void
    {
        $f = TuningFilter::fromQuery([]);

        self::assertSame(1, $f->tid);
        self::assertSame(TuningStatus::All, $f->status);
        self::assertSame([], $f->ignored);
    }

    public function testAllIsAnExplicitOptIn(): void
    {
        $f = TuningFilter::fromQuery(['team' => 'all']);

        self::assertNull($f->tid);
        self::assertSame([], $f->ignored);
    }

    public function testValidTeamIdIsAccepted(): void
    {
        $f = TuningFilter::fromQuery(['team' => '7']);

        self::assertSame(7, $f->tid);
        self::assertSame([], $f->ignored);
    }

    public function testUnknownTeamFallsBackToDefaultTeamAndIsReported(): void
    {
        $f = TuningFilter::fromQuery(['team' => '99']);

        self::assertSame(1, $f->tid);
        self::assertSame(['team'], $f->ignored);
    }

    public function testTeamZeroAndFortyAreNotFranchises(): void
    {
        foreach (['0', '29', '40'] as $raw) {
            $f = TuningFilter::fromQuery(['team' => $raw]);
            self::assertSame(1, $f->tid, $raw);
            self::assertSame(['team'], $f->ignored, $raw);
        }
        self::assertSame(28, TuningFilter::fromQuery(['team' => '28'])->tid);
    }

    public function testNonNumericTeamIsIgnored(): void
    {
        foreach (['1;DROP TABLE x', '7abc', '-3', '07', 'ALL', '7 '] as $raw) {
            $f = TuningFilter::fromQuery(['team' => $raw]);
            self::assertSame(1, $f->tid, $raw);
            self::assertSame(['team'], $f->ignored, $raw);
        }
    }

    public function testArrayTeamIsIgnored(): void
    {
        $f = TuningFilter::fromQuery(['team' => ['1']]);

        self::assertSame(1, $f->tid);
        self::assertSame(['team'], $f->ignored);
    }

    /**
     * @return array<string, array{string, TuningStatus}>
     */
    public static function statusWords(): array
    {
        return [
            'all' => ['all', TuningStatus::All],
            'shown' => ['shown', TuningStatus::Shown],
            'vetoed' => ['vetoed', TuningStatus::Vetoed],
            'hidden' => ['hidden', TuningStatus::Hidden],
        ];
    }

    #[DataProvider('statusWords')]
    public function testStatusAcceptsOnlyTheClosedSet(string $raw, TuningStatus $expected): void
    {
        $f = TuningFilter::fromQuery(['status' => $raw]);

        self::assertSame($expected, $f->status);
        self::assertSame([], $f->ignored);
    }

    public function testStatusIsCaseSensitive(): void
    {
        foreach (['HIDDEN', 'Shown', 'bogus'] as $raw) {
            $f = TuningFilter::fromQuery(['status' => $raw]);
            self::assertSame(TuningStatus::All, $f->status, $raw);
            self::assertSame(['status'], $f->ignored, $raw);
        }
        $array = TuningFilter::fromQuery(['status' => ['hidden']]);
        self::assertSame(TuningStatus::All, $array->status);
        self::assertSame(['status'], $array->ignored);
    }

    public function testBothParametersRefusedAreBothReported(): void
    {
        $f = TuningFilter::fromQuery(['team' => '40', 'status' => 'nope']);

        self::assertSame(1, $f->tid);
        self::assertSame(TuningStatus::All, $f->status);
        self::assertSame(['team', 'status'], $f->ignored);
    }

    public function testToQueryIsBuiltFromTypedFieldsNotRawInput(): void
    {
        self::assertSame(
            ['team' => '7', 'status' => 'vetoed'],
            TuningFilter::fromQuery(['team' => '7', 'status' => 'vetoed', 'extra' => 'x'])->toQuery()
        );
        self::assertSame(
            ['team' => '1', 'status' => 'all'],
            TuningFilter::fromQuery(['team' => '7 ', 'status' => 'Shown'])->toQuery()
        );
        self::assertSame(
            ['team' => 'all', 'status' => 'hidden'],
            TuningFilter::fromQuery(['team' => 'all', 'status' => 'hidden'])->toQuery()
        );
    }
}
