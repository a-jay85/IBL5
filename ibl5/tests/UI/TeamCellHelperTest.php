<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\TestCase;
use UI\TeamCellHelper;

class TeamCellHelperTest extends TestCase
{
    public function testRenderTeamCellProducesCorrectHtml(): void
    {
        $result = TeamCellHelper::renderTeamCell(1, 'Miami', 'FF0000', 'FFFFFF');

        $this->assertStringContainsString('class="ibl-team-cell--colored"', $result);
        $this->assertStringContainsString('background-color: #FF0000;', $result);
        $this->assertStringContainsString('color: #FFFFFF;', $result);
        $this->assertStringContainsString('images/logo/new1.png', $result);
        $this->assertStringContainsString('ibl-team-cell__text', $result);
        $this->assertStringContainsString('Miami', $result);
        $this->assertStringStartsWith('<td', $result);
        $this->assertStringEndsWith('</td>', $result);
    }

    public function testRenderTeamCellUsesDefaultTeamUrl(): void
    {
        $result = TeamCellHelper::renderTeamCell(5, 'Chicago', 'FF0000', '000000');

        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=5', $result);
    }

    public function testRenderTeamCellWithExtraClasses(): void
    {
        $result = TeamCellHelper::renderTeamCell(1, 'Miami', 'FF0000', 'FFFFFF', 'sticky-col');

        $this->assertStringContainsString('class="ibl-team-cell--colored sticky-col"', $result);
    }

    public function testRenderTeamCellWithCustomLinkUrl(): void
    {
        $customUrl = 'modules.php?name=Trading&amp;op=offertrade&amp;partner=Miami';
        $result = TeamCellHelper::renderTeamCell(1, 'Miami', 'FF0000', 'FFFFFF', '', $customUrl);

        $this->assertStringContainsString('href="' . $customUrl . '"', $result);
        $this->assertStringNotContainsString('name=Team', $result);
    }

    public function testRenderTeamCellWithCustomNameHtml(): void
    {
        $nameHtml = 'Miami <strong>(x)</strong>';
        $result = TeamCellHelper::renderTeamCell(1, 'Miami', 'FF0000', 'FFFFFF', '', '', $nameHtml);

        $this->assertStringContainsString($nameHtml, $result);
    }

    public function testRenderTeamCellSanitizesColors(): void
    {
        $result = TeamCellHelper::renderTeamCell(1, 'Miami', '<script>', 'FFFFFF');

        $this->assertStringContainsString('background-color: #000000;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRenderTeamCellEscapesTeamName(): void
    {
        $result = TeamCellHelper::renderTeamCell(1, 'Team <script>alert(1)</script>', 'FF0000', 'FFFFFF');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderTeamCellOrFreeAgentShowsFreeAgent(): void
    {
        $result = TeamCellHelper::renderTeamCellOrFreeAgent(0, '', 'FFFFFF', '000000');

        $this->assertSame('<td>Free Agent</td>', $result);
    }

    public function testRenderTeamCellOrFreeAgentShowsFreeAgentWithExtraClasses(): void
    {
        $result = TeamCellHelper::renderTeamCellOrFreeAgent(0, '', 'FFFFFF', '000000', 'sticky-col');

        $this->assertSame('<td class="sticky-col">Free Agent</td>', $result);
    }

    public function testRenderTeamCellOrFreeAgentWithCustomText(): void
    {
        $result = TeamCellHelper::renderTeamCellOrFreeAgent(0, '', 'FFFFFF', '000000', '', 'Unsigned');

        $this->assertSame('<td>Unsigned</td>', $result);
    }

    public function testRenderTeamCellOrFreeAgentRendersTeamWhenNotZero(): void
    {
        $result = TeamCellHelper::renderTeamCellOrFreeAgent(5, 'Chicago', 'FF0000', '000000');

        $this->assertStringContainsString('ibl-team-cell--colored', $result);
        $this->assertStringContainsString('Chicago', $result);
    }

    public function testTeamPageUrlBasic(): void
    {
        $result = TeamCellHelper::teamPageUrl(5);

        $this->assertSame('modules.php?name=Team&amp;op=team&amp;teamID=5', $result);
    }

    public function testTeamPageUrlWithYear(): void
    {
        $result = TeamCellHelper::teamPageUrl(5, 2024);

        $this->assertSame('modules.php?name=Team&amp;op=team&amp;teamID=5&amp;yr=2024', $result);
    }

    public function testTeamPageUrlWithNullYear(): void
    {
        $result = TeamCellHelper::teamPageUrl(5, null);

        $this->assertSame('modules.php?name=Team&amp;op=team&amp;teamID=5', $result);
    }

    public function testRenderTeamCellStripsHashFromColors(): void
    {
        $result = TeamCellHelper::renderTeamCell(1, 'Miami', '#FF0000', '#FFFFFF');

        $this->assertStringContainsString('background-color: #FF0000;', $result);
        $this->assertStringContainsString('color: #FFFFFF;', $result);
    }
}
