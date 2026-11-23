<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures;

interface CompliantA
{
}

interface CompliantB
{
}

final class ObjectId
{
}

final class Objects
{
}

class CompliantTypes
{
    private \Closure $poke;
    private CompliantA&CompliantB $both;

    public function __construct(callable $poke, CompliantA&CompliantB $both)
    {
        $this->poke = \Closure::fromCallable($poke);
        $this->both = $both;
    }

    public function __destruct()
    {
    }

    public function anything(mixed $value): mixed
    {
        return $value;
    }

    public function dnf((CompliantA&CompliantB)|null $value): void
    {
    }

    public function ids(ObjectId $id, Objects $all): void
    {
    }

    public function fluent(): self
    {
        return $this;
    }

    public static function make(): static
    {
        return new static('strlen', new class implements CompliantA, CompliantB {
        });
    }

    public function fail(): never
    {
        throw new \LogicException('never returns');
    }

    public function closures(): int
    {
        $closure = function ($a) {
            return $a;
        };
        $arrow = fn ($a) => $a;

        return $closure(1) + $arrow(2);
    }
}
