<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures;

class MissingTypesView
{
    private $db;
    var $legacy;

    public function __construct(private $repo)
    {
    }

    public function render($team, int $season): string
    {
        return '';
    }

    public function noReturn(int $x)
    {
    }

    public function spread(...$items): void
    {
    }
}

interface MissingTypesContract
{
    public function load($id);
}

abstract class MissingTypesAbstract
{
    abstract public function build();
}

trait MissingTypesTrait
{
    public function helper(): void
    {
    }

    public function untypedTraitMethod()
    {
    }
}

enum MissingTypesEnum: int
{
    case One = 1;

    public function label()
    {
        return 'one';
    }
}

function missingTypesFunction($value)
{
}

$anonymous = new class {
    public function run()
    {
    }
};
