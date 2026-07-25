<?php

declare(strict_types=1);

namespace Tests\GameBoxscore;

use GameBoxscore\Contracts\GameBoxscoreServiceInterface;
use GameBoxscore\GameBoxscoreView;
use PHPUnit\Framework\TestCase;

/**
 * Feeds hostile strings straight into the View (bypassing the Service) to prove
 * the View itself neutralizes them — escaping every DB-sourced string and
 * sanitizing every color before it reaches a CSS custom property.
 *
 * @phpstan-import-type GameBoxscoreViewModel from GameBoxscoreServiceInterface
 *
 * @covers \GameBoxscore\GameBoxscoreView
 */
class GameBoxscoreViewXssTest extends TestCase
{
    private GameBoxscoreView $view;

    protected function setUp(): void
    {
        $this->view = new GameBoxscoreView();
    }

    public function testEscapesHostilePlayerAndTeamNames(): void
    {
        $viewModel = $this->buildViewModel();
        $viewModel['awayPlayers'][0]['name'] = '<script>alert(1)</script>';
        $viewModel['awayPlayers'][0]['pos'] = '"><script>alert(2)</script>';
        $viewModel['awayTeam']['city'] = '"><img src=x onerror=alert(1)>';
        $viewModel['homeTeam']['name'] = '<script>alert(3)</script>';

        $output = $this->view->render($viewModel);

        // No hostile tag survives as markup, and no payload can break out of the
        // alt="..." attribute it lands in. The literal text "onerror=" may still
        // appear inside the escaped run — inert, because its < and " are entities.
        self::assertStringNotContainsString('<script>', $output);
        self::assertStringNotContainsString('<img src=x', $output);
        self::assertStringNotContainsString('<img src=x onerror=', $output);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
        self::assertStringContainsString('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $output);
    }

    public function testSanitizesCssInjectionInTeamColors(): void
    {
        $viewModel = $this->buildViewModel();
        $viewModel['awayTeam']['color1'] = 'fff;} body{display:none} .x{color:red';
        $viewModel['homeTeam']['color1'] = '"><style>';

        $output = $this->view->render($viewModel);

        // Both hostile colors collapse to the sanitizer's fallback.
        self::assertStringContainsString(
            'style="--team-color-primary: #000000; --team-color-secondary: #000000;"',
            $output,
        );
        self::assertStringNotContainsString('body{display:none}', $output);
        self::assertStringNotContainsString('<style>', $output);
    }

    public function testKeepsValidThreeAndSixDigitHexColors(): void
    {
        $viewModel = $this->buildViewModel();
        $viewModel['awayTeam']['color1'] = 'F0F';
        $viewModel['homeTeam']['color1'] = 'AABBCC';

        $output = $this->view->render($viewModel);

        self::assertStringContainsString(
            'style="--team-color-primary: #F0F; --team-color-secondary: #AABBCC;"',
            $output,
        );
    }

    /**
     * @return GameBoxscoreViewModel
     */
    private function buildViewModel(): array
    {
        $player = [
            'pid' => 10,
            'pos' => 'PG',
            'name' => 'Safe Name',
            'min' => 30,
            'fgm' => 9,
            'fga' => 17,
            'ftm' => 4,
            'fta' => 5,
            'tpm' => 2,
            'tpa' => 6,
            'pts' => 30,
            'orb' => 2,
            'reb' => 8,
            'ast' => 5,
            'stl' => 1,
            'blk' => 1,
            'tov' => 3,
            'pf' => 2,
        ];

        $totals = [
            'min' => 30,
            'fgm' => 9,
            'fga' => 17,
            'ftm' => 4,
            'fta' => 5,
            'tpm' => 2,
            'tpa' => 6,
            'pts' => 30,
            'orb' => 2,
            'reb' => 8,
            'ast' => 5,
            'stl' => 1,
            'blk' => 1,
            'tov' => 3,
            'pf' => 2,
        ];

        return [
            'found' => true,
            'date' => '2026-02-20',
            'gameOfThatDay' => 1,
            'awayTeam' => [
                'teamId' => 1,
                'name' => 'Away Team',
                'city' => 'Away City',
                'color1' => 'FF0000',
                'color2' => '0000FF',
                'score' => 105,
            ],
            'homeTeam' => [
                'teamId' => 2,
                'name' => 'Home Team',
                'city' => 'Home City',
                'color1' => '00FF00',
                'color2' => 'FFFF00',
                'score' => 98,
            ],
            'awayPlayers' => [$player],
            'homePlayers' => [$player],
            'awayTotals' => $totals,
            'homeTotals' => $totals,
        ];
    }
}
