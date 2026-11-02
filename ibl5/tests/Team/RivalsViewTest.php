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
        self::assertStringContainsString('>Team</strong>', $html);
        self::assertStringContainsString('>GM</strong>', $html);
        self::assertStringNotContainsString('op=gm&amp;gm=', $html);
        self::assertSame(1, substr_count($html, '>See all rivalries</a>'));
        self::assertStringContainsString('<div class="team-card__body team-rivals-footer"><a class="team-rivals-more" href="modules.php?name=Rivalries&amp;op=team&amp;team=3">', $html);
        self::assertStringContainsString('<span class="award-year">7-5</span>', $html);
        self::assertStringNotContainsString('ibl-badge', $html);
    }

    public function testTeamAndGmSectionsShareOneGrid(): void
    {
        $html = (new RivalsView())->render([self::rivalry('3', '7')], 'Rival Gm', [self::rivalry('Rival Gm', 'Mover Gm')], '');

        $grid = substr($html, (int) strpos($html, '<div class="team-rivals-grid">'));
        self::assertStringStartsWith('<div class="team-rivals-grid"><div class="team-rivals-section">', $grid);
        self::assertSame(2, substr_count($grid, '<div class="team-rivals-section">'));
    }

    public function testRendersEmptyStringWhenNoRivals(): void
    {
        self::assertSame('', (new RivalsView())->render([], 'Rival Gm', [], ''));
    }

    public function testGmOnlyCardLinksToGmRivalries(): void
    {
        $html = (new RivalsView())->render([], 'Rival Gm', [self::rivalry('Rival Gm', 'Mover Gm')], '');

        self::assertStringContainsString('href="modules.php?name=Rivalries&amp;op=gm&amp;gm=Rival+Gm">See all rivalries</a>', $html);
    }

    public function testFranchiseOnlyCardWithoutGmLinksToFranchiseRivalries(): void
    {
        $html = (new RivalsView())->render([self::rivalry('3', '7')], null, [self::rivalry('Rival Gm', 'Mover Gm')], '');

        self::assertStringNotContainsString('>GM</strong>', $html);
        self::assertStringContainsString('op=team&amp;team=3">See all rivalries</a>', $html);
    }

    public function testEscapesHostileGmNameInCard(): void
    {
        $html = (new RivalsView())->render([], '<script>x</script>', [self::rivalry('Zed Gm', '<script>x</script>')], '');

        self::assertStringNotContainsString('op=team&amp;', $html);
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
