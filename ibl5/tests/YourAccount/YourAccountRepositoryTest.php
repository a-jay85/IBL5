<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\YourAccountRepository;

class YourAccountRepositoryTest extends TestCase
{
    public function testRepositoryCanBeInstantiated(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $repo = new YourAccountRepository($stub);
        $this->assertInstanceOf(YourAccountRepository::class, $repo);
    }
}
