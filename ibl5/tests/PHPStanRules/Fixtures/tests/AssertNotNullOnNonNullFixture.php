<?php

declare(strict_types=1);

final class AssertNotNullOnNonNullFixture
{
    public function testNonNullValue(string $value): void
    {
        $this->assertNotNull($value);
    }
}
