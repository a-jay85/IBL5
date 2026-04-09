<?php

declare(strict_types=1);

final class MeaningfulAssertionFixture
{
    public function testSomething(): void
    {
        $result = 2 + 2;
        $this->assertEquals(4, $result);
    }
}
