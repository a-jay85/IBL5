<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures;

class BareObjectMock
{
    private object $handle;

    public function __construct(private ?object $user)
    {
    }

    public function bind(object $stmt): void
    {
    }

    public function fetch(): object
    {
        return new \stdClass();
    }

    public function maybeFetch(): ?object
    {
        return null;
    }

    public function mixedInput(array|object|null $value): void
    {
    }
}
