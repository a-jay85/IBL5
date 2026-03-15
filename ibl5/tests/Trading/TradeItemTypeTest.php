<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeItemType;

class TradeItemTypeTest extends TestCase
{
    public function testDraftPickValue(): void
    {
        $this->assertSame('0', TradeItemType::DraftPick->value);
    }

    public function testPlayerValue(): void
    {
        $this->assertSame('1', TradeItemType::Player->value);
    }

    public function testCashValue(): void
    {
        $this->assertSame('cash', TradeItemType::Cash->value);
    }

    public function testFromFormIntPick(): void
    {
        $this->assertSame(TradeItemType::DraftPick, TradeItemType::fromFormInt(0));
    }

    public function testFromFormIntPlayer(): void
    {
        $this->assertSame(TradeItemType::Player, TradeItemType::fromFormInt(1));
    }

    public function testFromFormIntInvalidThrows(): void
    {
        $this->expectException(\ValueError::class);
        TradeItemType::fromFormInt(99);
    }

    public function testFromDatabaseString(): void
    {
        $this->assertSame(TradeItemType::Cash, TradeItemType::from('cash'));
        $this->assertSame(TradeItemType::Player, TradeItemType::from('1'));
        $this->assertSame(TradeItemType::DraftPick, TradeItemType::from('0'));
    }
}
