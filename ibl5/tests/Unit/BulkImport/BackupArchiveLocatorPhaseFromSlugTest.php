<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\BackupArchiveLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BackupArchiveLocatorPhaseFromSlugTest extends TestCase
{
    #[DataProvider('slugProvider')]
    public function testPhaseFromSlug(string $slug, ?string $expectedPhase): void
    {
        self::assertSame($expectedPhase, BackupArchiveLocator::phaseFromSlug($slug));
    }

    /** @return array<string, array{string, string|null}> */
    public static function slugProvider(): array
    {
        return [
            'reg-sim maps to Regular Season'             => ['reg-sim', 'Regular Season'],
            'REG-SIM case-insensitive maps to Regular Season' => ['REG-SIM', 'Regular Season'],
            'offseason-postdraft maps to Draft'          => ['offseason-postdraft', 'Draft'],
            'offseason-postfa maps to Free Agency'       => ['offseason-postfa', 'Free Agency'],
            'preseason maps to Preseason'                => ['preseason', 'Preseason'],
            'heat maps to HEAT'                          => ['heat', 'HEAT'],
            'playoffs maps to Playoffs'                  => ['playoffs', 'Playoffs'],
            'unrecognized slug returns null'             => ['nope', null],
            'empty string returns null'                  => ['', null],
        ];
    }
}
