<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\TestCase;
use SeasonHighs\Contracts\SeasonHighsViewInterface;
use SeasonHighs\SeasonHighsView;

/**
 * @covers \SeasonHighs\SeasonHighsView
 */
class SeasonHighsViewTest extends TestCase
{
    private SeasonHighsView $view;

    protected function setUp(): void
    {
        $this->view = new SeasonHighsView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(SeasonHighsViewInterface::class, $this->view);
    }

    public function testRenderShowsTitle(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString('Season Highs', $html);
    }

    public function testRenderShowsPlayerHighsSection(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString("Players'", $html);
        $this->assertStringContainsString('Regular Season', $html);
    }

    public function testRenderShowsTeamHighsSection(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString("Teams'", $html);
    }

    public function testRenderShowsStatName(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry()]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('POINTS', $html);
    }

    public function testRenderShowsPlayerName(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['name' => 'LeBron James', 'pid' => 1])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('LeBron James', $html);
    }

    public function testRenderShowsStatValue(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['value' => 55])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('>55<', $html);
    }

    public function testRenderLinksPlayerToProfile(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['pid' => 42])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('pid=42', $html);
    }

    public function testRenderLinksDateToBoxScore(): void
    {
        $data = [
            'playerHighs' => ['POINTS' => [self::createHighEntry(['boxId' => 99])]],
            'teamHighs' => [],
        ];

        $html = $this->view->render('Regular Season', $data);

        $this->assertStringContainsString('box99.htm', $html);
    }

    public function testRenderHandlesEmptyData(): void
    {
        $html = $this->view->render('Regular Season', self::createEmptyData());

        $this->assertStringContainsString('Season Highs', $html);
    }

    /**
     * @return array{playerHighs: array<string, list<mixed>>, teamHighs: array<string, list<mixed>>}
     */
    private static function createEmptyData(): array
    {
        return [
            'playerHighs' => [],
            'teamHighs' => [],
        ];
    }

    /**
     * @return array{name: string, date: string, value: int, pid?: int, tid?: int, teamname?: string, color1?: string, color2?: string, boxId?: int}
     */
    private static function createHighEntry(array $overrides = []): array
    {
        /** @var array{name: string, date: string, value: int, pid?: int, tid?: int, teamname?: string, color1?: string, color2?: string, boxId?: int} */
        return array_merge([
            'name' => 'Test Player',
            'date' => '2024-12-15',
            'value' => 30,
            'pid' => 1,
            'tid' => 1,
            'teamname' => 'Hawks',
            'color1' => 'FF0000',
            'color2' => '000000',
            'boxId' => 100,
        ], $overrides);
    }
}
