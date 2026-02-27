<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameTextGenerator;

/**
 * Tests for OneOnOneGameTextGenerator
 */
final class OneOnOneGameTextGeneratorTest extends TestCase
{
    private OneOnOneGameTextGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OneOnOneGameTextGenerator();
    }

    public function testGetThreePointTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getThreePointText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetOutsideTwoTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getOutsideTwoText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetDriveTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getDriveText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetPostTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getPostText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetMadeShotTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getMadeShotText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetMissedShotTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getMissedShotText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetBlockTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getBlockText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetStealTextReturnsNonEmptyString(): void
    {
        $text = $this->generator->getStealText();

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function testGetCoinFlipTextForHeads(): void
    {
        $text = $this->generator->getCoinFlipText(true, 'Player One', 'Player Two');

        $this->assertStringContainsString('heads', $text);
        $this->assertStringContainsString('Player One', $text);
        $this->assertStringContainsString('gets the ball to start', $text);
    }

    public function testGetCoinFlipTextForTails(): void
    {
        $text = $this->generator->getCoinFlipText(false, 'Player One', 'Player Two');

        $this->assertStringContainsString('tails', $text);
        $this->assertStringContainsString('Player Two', $text);
        $this->assertStringContainsString('gets the ball to start', $text);
    }

    public function testGetScoreTextFormatsCorrectly(): void
    {
        $text = $this->generator->getScoreText('Michael Jordan', 15, 'LeBron James', 12);

        $this->assertStringContainsString('Michael Jordan 15', $text);
        $this->assertStringContainsString('LeBron James 12', $text);
        $this->assertStringContainsString('SCORE:', $text);
    }

    public function testGetFoulTextIncludesPlayerNames(): void
    {
        $text = $this->generator->getFoulText('Defender', 'Attacker');

        $this->assertStringContainsString('Defender', $text);
        $this->assertStringContainsString('Attacker', $text);
        $this->assertStringContainsString('fouls', $text);
    }

    public function testGetStealPlayTextIncludesPlayerNames(): void
    {
        $text = $this->generator->getStealPlayText('Defender', 'Attacker');

        $this->assertStringContainsString('Defender', $text);
        $this->assertStringContainsString('Attacker', $text);
    }

    public function testGetReboundTextForOffensiveRebound(): void
    {
        $text = $this->generator->getReboundText('Player Name', true);

        $this->assertStringContainsString('Player Name', $text);
        $this->assertStringContainsString('offensive', $text);
        $this->assertStringContainsString('rebound', $text);
    }

    public function testGetReboundTextForDefensiveRebound(): void
    {
        $text = $this->generator->getReboundText('Player Name', false);

        $this->assertStringContainsString('Player Name', $text);
        $this->assertStringContainsString('rebound', $text);
        $this->assertStringNotContainsString('offensive', $text);
    }
}
