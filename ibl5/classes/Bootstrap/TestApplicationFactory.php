<?php

declare(strict_types=1);

namespace Bootstrap;

final class TestApplicationFactory
{
    public static function build(string $basePath): Application
    {
        $app = new Application();
        $app->addStep(new TestEnvironmentBootstrap());
        $app->addStep(new TestAliasesBootstrap());
        $app->addStep(new TestConfigBootstrap($basePath));
        return $app;
    }
}
