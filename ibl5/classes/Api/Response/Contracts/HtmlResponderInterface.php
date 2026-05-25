<?php

declare(strict_types=1);

namespace Api\Response\Contracts;

interface HtmlResponderInterface
{
    public function html(string $content): void;

    public function json(mixed $data): void;
}
