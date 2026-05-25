<?php

declare(strict_types=1);

namespace Api\Response;

use Api\Response\Contracts\HtmlResponderInterface;

class HtmlResponder implements HtmlResponderInterface
{
    public function html(string $content): void
    {
        echo $content;
    }

    public function json(mixed $data): void
    {
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }
}
