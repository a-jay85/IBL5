<?php

declare(strict_types=1);

final class EmptyNonTestMethodFixture
{
    public function setUp(): void
    {
    }

    public function testSomething(): void
    {
        $this->assertEquals(4, 2 + 2);
    }
}
