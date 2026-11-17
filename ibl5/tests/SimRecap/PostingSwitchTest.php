<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\PostingSwitch;

/**
 * PostingSwitch::isOn() is true for exactly 'On' and fails closed for every other stored value,
 * including a missing row.
 */
class PostingSwitchTest extends TestCase
{
    public function testIsOnTrueWhenSettingIsOn(): void
    {
        $settings = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $settings->expects($this->once())->method('getSetting')->with('Sim Recap Discord Posting')->willReturn('On');

        $this->assertTrue((new PostingSwitch($settings))->isOn());
    }

    #[DataProvider('nonOnValueProvider')]
    public function testIsOnOnlyForExactOnValue(?string $stored): void
    {
        $settings = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $settings->method('getSetting')->willReturn($stored);

        $this->assertFalse((new PostingSwitch($settings))->isOn());
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function nonOnValueProvider(): array
    {
        return [
            'missing row'      => [null],
            'Off'              => ['Off'],
            'lowercase on'     => ['on'],
            'uppercase ON'     => ['ON'],
            'empty'            => [''],
            'trailing space'   => ['On '],
        ];
    }
}
