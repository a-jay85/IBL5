<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\Views\CardBaseStyles;
use Player\Views\TeamColorHelper;

/**
 * @covers \Player\Views\CardBaseStyles
 */
class CardBaseStylesTest extends TestCase
{
    /**
     * @return array{name: string, nickname: string, position: string, teamName: string, teamID: int, age: string, height: string, weight: string, college: string, draftYear: int, draftRound: string, draftPick: string, draftTeam: string, imageUrl: string}
     */
    private function createPlayerData(int $teamID = 7, string $teamName = 'Miami'): array
    {
        return [
            'name' => 'Test Player',
            'nickname' => 'T.P.',
            'position' => 'SG',
            'teamName' => $teamName,
            'teamID' => $teamID,
            'age' => '25',
            'height' => '6\'4"',
            'weight' => '200',
            'college' => 'Duke',
            'draftYear' => 2020,
            'draftRound' => '1',
            'draftPick' => '5',
            'draftTeam' => 'Chicago',
            'imageUrl' => './images/player/100.png',
        ];
    }

    public function testRenderCardTopContainsTeamLogoForRosteredPlayer(): void
    {
        $playerData = $this->createPlayerData(7, 'Miami');

        $html = CardBaseStyles::renderCardTop($playerData);

        $this->assertStringContainsString('card-team-logo', $html);
        $this->assertStringContainsString('images/logo/new7.png', $html);
        $this->assertStringContainsString('href="modules.php?name=Team&amp;op=team&amp;teamID=7"', $html);
    }

    public function testRenderCardTopOmitsTeamLogoForFreeAgent(): void
    {
        $playerData = $this->createPlayerData(0, 'Free Agents');

        $html = CardBaseStyles::renderCardTop($playerData);

        $this->assertStringNotContainsString('card-team-logo', $html);
        $this->assertStringNotContainsString('images/logo/new', $html);
    }

    public function testRenderCardTopContainsPlayerName(): void
    {
        $playerData = $this->createPlayerData();

        $html = CardBaseStyles::renderCardTop($playerData);

        $this->assertStringContainsString('Test Player', $html);
        $this->assertStringContainsString('<h2>', $html);
    }

    public function testRenderCardTopContainsStatsGrid(): void
    {
        $playerData = $this->createPlayerData(7, 'Miami');

        $html = CardBaseStyles::renderCardTop($playerData);

        $this->assertStringContainsString('stats-grid', $html);
        $this->assertStringContainsString('Miami', $html);
    }

    public function testRenderCardTopContainsDraftInfo(): void
    {
        $playerData = $this->createPlayerData();

        $html = CardBaseStyles::renderCardTop($playerData);

        $this->assertStringContainsString('draft-info', $html);
        $this->assertStringContainsString('Chicago', $html);
        $this->assertStringContainsString('2020', $html);
    }

    public function testGetStylesReturnsEmptyString(): void
    {
        $colorScheme = TeamColorHelper::getDefaultColorScheme();

        $css = CardBaseStyles::getStyles($colorScheme);

        $this->assertSame('', $css);
    }

    public function testGetCardCssPropertiesContainsCustomProperties(): void
    {
        $colorScheme = TeamColorHelper::getDefaultColorScheme();

        $props = CardBaseStyles::getCardCssProperties($colorScheme);

        $this->assertStringContainsString('--card-grad-start:', $props);
        $this->assertStringContainsString('--card-grad-mid:', $props);
        $this->assertStringContainsString('--card-grad-end:', $props);
        $this->assertStringContainsString('--card-border:', $props);
        $this->assertStringContainsString('--card-border-rgb:', $props);
        $this->assertStringContainsString('--card-accent:', $props);
        $this->assertStringContainsString('--card-text:', $props);
        $this->assertStringContainsString('--card-text-muted:', $props);
    }
}
