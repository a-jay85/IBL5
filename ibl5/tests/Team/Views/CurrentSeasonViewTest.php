<?php

declare(strict_types=1);

namespace Tests\Team\Views;

use PHPUnit\Framework\TestCase;
use Team\Views\CurrentSeasonView;

class CurrentSeasonViewTest extends TestCase
{
    private CurrentSeasonView $view;

    protected function setUp(): void
    {
        $this->view = new CurrentSeasonView();
    }

    /**
     * @return array{teamName: string, fka: ?string, wins: int, losses: int, arena: string, capacity: int, conference: string, conferencePosition: int, division: string, divisionPosition: int, divisionGB: float, homeRecord: string, awayRecord: string, lastWin: int, lastLoss: int}
     */
    private function buildData(
        string $teamName = 'Miami Heat',
        ?string $fka = null,
        int $wins = 40,
        int $losses = 20,
        string $arena = 'American Airlines Arena',
        int $capacity = 19600,
        string $conference = 'Eastern',
        int $conferencePosition = 3,
        string $division = 'Atlantic',
        int $divisionPosition = 1,
        float $divisionGB = 0.0,
        string $homeRecord = '25-5',
        string $awayRecord = '15-15',
        int $lastWin = 7,
        int $lastLoss = 3,
    ): array {
        return [
            'teamName' => $teamName,
            'fka' => $fka,
            'wins' => $wins,
            'losses' => $losses,
            'arena' => $arena,
            'capacity' => $capacity,
            'conference' => $conference,
            'conferencePosition' => $conferencePosition,
            'division' => $division,
            'divisionPosition' => $divisionPosition,
            'divisionGB' => $divisionGB,
            'homeRecord' => $homeRecord,
            'awayRecord' => $awayRecord,
            'lastWin' => $lastWin,
            'lastLoss' => $lastLoss,
        ];
    }

    public function testRendersAllFields(): void
    {
        $html = $this->view->render($this->buildData());

        $this->assertStringContainsString('team-info-list', $html);
        $this->assertStringContainsString('Miami Heat', $html);
        $this->assertStringContainsString('40-20', $html);
        $this->assertStringContainsString('American Airlines Arena', $html);
        $this->assertStringContainsString('19600', $html);
        $this->assertStringContainsString('Eastern (3rd)', $html);
        $this->assertStringContainsString('Atlantic (1st)', $html);
        $this->assertStringContainsString('25-5', $html);
        $this->assertStringContainsString('15-15', $html);
        $this->assertStringContainsString('7-3', $html);
    }

    public function testIncludesFkaWhenPresent(): void
    {
        $html = $this->view->render($this->buildData(fka: 'Charlotte Hornets (2005-2010)'));

        $this->assertStringContainsString('f.k.a.', $html);
        $this->assertStringContainsString('Charlotte Hornets (2005-2010)', $html);
    }

    public function testOmitsFkaWhenNull(): void
    {
        $html = $this->view->render($this->buildData(fka: null));

        $this->assertStringNotContainsString('f.k.a.', $html);
    }

    public function testOmitsCapacityWhenZero(): void
    {
        $html = $this->view->render($this->buildData(capacity: 0));

        $this->assertStringNotContainsString('Capacity', $html);
    }

    public function testEscapesHtmlInValues(): void
    {
        $html = $this->view->render($this->buildData(teamName: '<script>alert(1)</script>'));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testOrdinalSuffixes(): void
    {
        // 1st
        $html = $this->view->render($this->buildData(conferencePosition: 1));
        $this->assertStringContainsString('(1st)', $html);

        // 2nd
        $html = $this->view->render($this->buildData(conferencePosition: 2));
        $this->assertStringContainsString('(2nd)', $html);

        // 3rd
        $html = $this->view->render($this->buildData(conferencePosition: 3));
        $this->assertStringContainsString('(3rd)', $html);

        // 11th (special case)
        $html = $this->view->render($this->buildData(conferencePosition: 11));
        $this->assertStringContainsString('(11th)', $html);
    }
}
