<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\Module\Factories;

final class RequestPassThroughFactory
{
    public function __construct(
        private readonly \Module\ModuleServices $services,
        private readonly ServerNamedHolder $holder,
    ) {
    }

    public function request(): \Http\HttpRequest
    {
        return $this->services->request();
    }

    public function consumer(): RequestConsumer
    {
        return new RequestConsumer($this->services->request());
    }

    public function notARequest(): string
    {
        return $this->holder->server('HTTP_HOST');
    }
}

final class RequestConsumer
{
    public function __construct(public readonly \Http\HttpRequest $request)
    {
    }
}

final class ServerNamedHolder
{
    public function server(string $key): string
    {
        return $key;
    }
}
