<?php

declare(strict_types=1);

namespace Tests\EventLog;

use EventLog\RouteNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteNameNormalizerTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function normalizeProvider(): array
    {
        return [
            'Your_Account (underscored)'    => ['Your_Account', 'your_account'],
            'YourAccount (PascalCase)'       => ['YourAccount', 'your_account'],
            'Depth_Chart_Entry (underscored)'=> ['Depth_Chart_Entry', 'depth_chart_entry'],
            'DepthChartEntry (PascalCase)'   => ['DepthChartEntry', 'depth_chart_entry'],
            'team (lowercase)'               => ['team', 'team'],
            'Team (capitalized)'             => ['Team', 'team'],
            'ApiKeys (acronym+camel)'        => ['ApiKeys', 'api_keys'],
            'GMContactList (acronym)'        => ['GMContactList', 'gm_contact_list'],
            'OneOnOneGame'                   => ['OneOnOneGame', 'one_on_one_game'],
            'TeamOffDefStats'                => ['TeamOffDefStats', 'team_off_def_stats'],
            // Negative paths
            'underscore only → null'         => ['_', null],
            'hyphens only → null'            => ['---', null],
            'collapse and trim underscores'  => ['__A__B__', 'a_b'],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $input, ?string $expected): void
    {
        self::assertSame($expected, RouteNameNormalizer::normalize($input));
    }

    public function testLongPascalCaseIsNotTruncatedByNormalizer(): void
    {
        // Normalizer does not truncate — the caller does.
        // A 20-char PascalCase grows past its raw length after underscore insertion.
        $input = 'TeamOffDefStatsReport';  // 21 chars
        $result = RouteNameNormalizer::normalize($input);
        self::assertNotNull($result);
        self::assertGreaterThan(strlen($input), strlen($result));
    }
}
