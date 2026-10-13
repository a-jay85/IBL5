<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use BasketballStats\Tables\Per36Minutes;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \BasketballStats\Tables\Per36Minutes
 */
#[AllowMockObjectsWithoutExpectations]
class Per36MinutesTest extends TestCase
{
    private function createMockTeam(): \Team\Team
    {
        $team = $this->createMock(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;
        $team->name = 'Test Team';
        return $team;
    }

    /**
     * Characterization pin: render() with no $ariaLabel arg emits table open tag with NO aria-label
     */
    public function testTableOpenTagHasNoAriaLabel(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $html = Per36Minutes::render($mockDb, [], $this->createMockTeam(), '', [], '');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }

    private function makeTeam(string $name = 'Test Team', string $color1 = 'FF0000'): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = $color1;
        $team->color2 = '0000FF';
        $team->teamid = 7;
        $team->name = $name;

        return $team;
    }

    private function makeDb(): MockDatabase
    {
        return new MockDatabase();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function goldenCases(): array
    {
        return [
            'aria' => ['aria', '2023', 'Per 36 minutes'],
            'no-aria' => ['no-aria', '2023', ''],
        ];
    }

    #[DataProvider('goldenCases')]
    public function testRenderMatchesGolden(string $case, string $yr, string $ariaLabel): void
    {
        $html = Per36Minutes::render($this->makeDb(), [], $this->makeTeam(), $yr, [], '', $ariaLabel);

        $goldenPath = __DIR__ . '/fixtures/per36-minutes-' . $case . '.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testAriaLabelIsEscaped(): void
    {
        $html = Per36Minutes::render($this->makeDb(), [], $this->makeTeam(), '2023', [], '', '"><i>');

        $this->assertStringContainsString('aria-label="&quot;&gt;&lt;i&gt;"', $html);
        $this->assertStringNotContainsString('<i>', $html);
    }
}
