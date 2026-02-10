<?php

declare(strict_types=1);

namespace Tests\ContractList;

use ContractList\ContractListView;
use ContractList\Contracts\ContractListViewInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ContractList\ContractListView
 */
class ContractListViewTest extends TestCase
{
    private ContractListView $view;

    protected function setUp(): void
    {
        $this->view = new ContractListView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(ContractListViewInterface::class, $this->view);
    }

    public function testRenderOutputsTable(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderShowsTitle(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('Master Contract List', $html);
    }

    public function testRenderShowsTableHeaders(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('Player', $html);
        $this->assertStringContainsString('Pos', $html);
        $this->assertStringContainsString('Bird', $html);
        $this->assertStringContainsString('Year1', $html);
    }

    public function testRenderShowsPlayerName(): void
    {
        $data = self::createRenderData([
            'contracts' => [self::createContract(['name' => 'Kevin Durant'])],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('Kevin Durant', $html);
    }

    public function testRenderShowsCapTotals(): void
    {
        $data = self::createRenderData([
            'capTotals' => ['cap1' => 123.45, 'cap2' => 0.0, 'cap3' => 0.0, 'cap4' => 0.0, 'cap5' => 0.0, 'cap6' => 0.0],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('Cap Totals', $html);
        $this->assertStringContainsString('123.45', $html);
    }

    public function testRenderShowsAverageCaps(): void
    {
        $data = self::createRenderData([
            'avgCaps' => ['acap1' => 45.67, 'acap2' => 0.0, 'acap3' => 0.0, 'acap4' => 0.0, 'acap5' => 0.0, 'acap6' => 0.0],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('Average Team Cap', $html);
        $this->assertStringContainsString('45.67', $html);
    }

    public function testRenderShowsContractValues(): void
    {
        $data = self::createRenderData([
            'contracts' => [self::createContract(['con1' => 500, 'con2' => 600])],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('>500<', $html);
        $this->assertStringContainsString('>600<', $html);
    }

    public function testRenderIncludesSortableClass(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('sortable', $html);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{contracts: list<array{pid: int, name: string, pos: string, teamname: string, tid: int, team_city: string, color1: string, color2: string, bird: string, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int}>, capTotals: array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float}, avgCaps: array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float}}
     */
    private static function createRenderData(array $overrides = []): array
    {
        $defaults = [
            'contracts' => [],
            'capTotals' => ['cap1' => 0.0, 'cap2' => 0.0, 'cap3' => 0.0, 'cap4' => 0.0, 'cap5' => 0.0, 'cap6' => 0.0],
            'avgCaps' => ['acap1' => 0.0, 'acap2' => 0.0, 'acap3' => 0.0, 'acap4' => 0.0, 'acap5' => 0.0, 'acap6' => 0.0],
        ];

        /** @var array{contracts: list<array{pid: int, name: string, pos: string, teamname: string, tid: int, team_city: string, color1: string, color2: string, bird: string, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int}>, capTotals: array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float}, avgCaps: array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float}} */
        return array_merge($defaults, $overrides);
    }

    /**
     * @return array{pid: int, name: string, pos: string, teamname: string, tid: int, team_city: string, color1: string, color2: string, bird: string, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int}
     */
    private static function createContract(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, pos: string, teamname: string, tid: int, team_city: string, color1: string, color2: string, bird: string, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int} */
        return array_merge([
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'G',
            'teamname' => 'Hawks',
            'tid' => 1,
            'team_city' => 'Atlanta',
            'color1' => 'FF0000',
            'color2' => '000000',
            'bird' => 'Yes',
            'con1' => 500,
            'con2' => 550,
            'con3' => 600,
            'con4' => 0,
            'con5' => 0,
            'con6' => 0,
        ], $overrides);
    }
}
