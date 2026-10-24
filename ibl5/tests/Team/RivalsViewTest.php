<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Rivalries\Contracts\RivalryScorerInterface;
use Team\Views\RivalsView;

/**
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 */
final class RivalsViewTest extends TestCase
{
    public function testRendersTopThreeFranchiseAndGmRivalLinks(): void
    {
        $franchise = [self::rivalry('3', '7'), self::rivalry('3', '9'), self::rivalry('3', '12')];
        $gm = [self::rivalry('Rival Gm', 'Mover Gm'), self::rivalry('Rival Gm', 'Zed Gm')];

        $html = (new RivalsView())->render($franchise, 'Rival Gm', $gm, '--team-color-primary: #000000;');

        self::assertStringContainsString('team-card', $html);
        self::assertStringContainsString('Top Rivals', $html);
        self::assertStringContainsString('op=teams&amp;a=3&amp;b=7', $html);
        self::assertStringContainsString('op=gms&amp;a=Rival+Gm', $html);
        self::assertStringContainsString('op=gm&amp;gm=Rival+Gm', $html);
    }

    public function testRendersEmptyStringWhenNoRivals(): void
    {
        self::assertSame('', (new RivalsView())->render([], 'Rival Gm', [], ''));
    }

    public function testEscapesHostileGmNameInCard(): void
    {
        $html = (new RivalsView())->render([], '<script>x</script>', [self::rivalry('<script>x</script>', 'Zed Gm')], '');

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>x', $html);
    }

    /**
     * @return ScoredRivalry
     */
    private static function rivalry(string $a, string $b): array
    {
        return ['a' => $a, 'b' => $b, 'a_label' => $a, 'b_label' => $b, 'games' => 12, 'a_wins' => 7, 'b_wins' => 5, 'score' => 40];
    }
}
