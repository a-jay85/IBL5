<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\Module\Factories;

final class RequestReadFactory
{
    public function __construct(private readonly \Http\HttpRequest $request)
    {
    }

    public function host(): mixed
    {
        return $this->request->server('HTTP_HOST');
    }

    public function op(): mixed
    {
        return $this->request->request('op');
    }

    public function twoStep(): mixed
    {
        $r = $this->request;
        return $r->get('pid');
    }

    public function everything(): mixed
    {
        return $this->request->allPost();
    }
}
