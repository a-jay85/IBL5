<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

class TestAliasesBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        class_alias('Tests\\WideUnit\\Mocks\\MockDatabase', 'MockDatabase');
        class_alias('Tests\\WideUnit\\Mocks\\MockDatabaseResult', 'MockDatabaseResult');
        class_alias('Tests\\WideUnit\\Mocks\\MockPreparedStatement', 'MockPreparedStatement');
        class_alias('Tests\\WideUnit\\Mocks\\MockMysqliResult', 'MockMysqliResult');
        class_alias('Tests\\WideUnit\\Mocks\\UI', 'UI');
        class_alias('Tests\\WideUnit\\Mocks\\Season', 'Season\\Season');
    }
}
