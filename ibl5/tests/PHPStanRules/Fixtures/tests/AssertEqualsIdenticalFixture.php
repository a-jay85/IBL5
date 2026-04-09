<?php

declare(strict_types=1);

final class AssertEqualsIdenticalFixture
{
    public function testAssertEqualsIdentical(): void
    {
        $this->assertEquals(42, 42);
    }
}
