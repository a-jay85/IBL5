<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Tests\Season\RealSeasonProcess;

/**
 * A settings write must drop the cached Season so the next read in the same request sees it.
 *
 * PHPUnit aliases Season\Season to a mock that never queries, so each scenario runs in a child
 * process with the real class. The child opens its own connection, writes through
 * LeagueControlPanelRepository, reads through SharedSeason, then restores the original value.
 * LeagueControlPanelRepository::transactional() starts its own transaction, so a surrounding
 * rollback would not isolate the write; the child restores by hand instead.
 */
#[Group('database')]
final class SharedSeasonWriteThenReadTest extends DatabaseTestCase
{
    public function testSetSeasonPhaseRebuildsSharedSeason(): void
    {
        $result = $this->runScenario(
            'phase',
            '$repo->setSeasonPhase("Playoffs");',
            '$repo->setSeasonPhase($original);',
        );

        self::assertFalse($result['same']);
        self::assertSame('Playoffs', $result['after']);
    }

    public function testSetAllowTradesRebuildsSharedSeason(): void
    {
        $result = $this->runScenario(
            'allowTrades',
            '$repo->setAllowTrades($original === "Yes" ? "No" : "Yes");',
            '$repo->setAllowTrades($original);',
        );

        self::assertFalse($result['same']);
        self::assertNotSame($result['before'], $result['after']);
    }

    public function testSetShowDraftLinkRebuildsSharedSeason(): void
    {
        $result = $this->runScenario(
            'showDraftLink',
            '$repo->setShowDraftLink($original === "On" ? "Off" : "On");',
            '$repo->setShowDraftLink($original);',
        );

        self::assertFalse($result['same']);
        self::assertNotSame($result['before'], $result['after']);
    }

    public function testReadWithoutWriteKeepsSharedInstance(): void
    {
        $result = $this->runScenario('phase', '', '');

        self::assertTrue($result['same']);
        self::assertSame($result['before'], $result['after']);
    }

    /**
     * @return array{same: bool, before: string, after: string}
     */
    private function runScenario(string $property, string $write, string $restore): array
    {
        $result = RealSeasonProcess::run(<<<PHP
            \$db = new mysqli();
            \$db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
            \$db->real_connect(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
            Season\\SharedSeason::enable();
            \$repo = new LeagueControlPanel\\LeagueControlPanelRepository(\$db);
            \$first = Season\\SharedSeason::get(\$db);
            \$original = \$first->$property;
            try {
                $write
                \$second = Season\\SharedSeason::get(\$db);
                echo json_encode([
                    'same' => \$first === \$second,
                    'before' => \$original,
                    'after' => \$second->$property,
                ]);
            } finally {
                $restore
            }
            PHP);

        /** @var array{same: bool, before: string, after: string} $result */
        return $result;
    }
}
