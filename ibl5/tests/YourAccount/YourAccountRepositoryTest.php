<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\YourAccountRepository;

class YourAccountRepositoryTest extends TestCase
{
    public function testRepositoryCanBeInstantiated(): void
    {
        self::assertContains(
            \BaseMysqliRepository::class,
            (array) class_parents(YourAccountRepository::class)
        );
    }
}
