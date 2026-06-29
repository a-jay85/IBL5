<?php

declare(strict_types=1);

final class AssertNotNullNullableFixture
{
    public function testNullableValue(?string $value): void
    {
        $this->assertNotNull($value);
    }

    public function testMixedValue($value): void
    {
        $this->assertNotNull($value);
    }
}
