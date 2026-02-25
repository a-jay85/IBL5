<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\PlrParserRepository;

class PlrParserRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(PlrParserRepository::class, \BaseMysqliRepository::class)
        );

        $interfaces = class_implements(PlrParserRepository::class);
        $this->assertIsArray($interfaces);
        $this->assertArrayHasKey(PlrParserRepositoryInterface::class, $interfaces);
    }
}
