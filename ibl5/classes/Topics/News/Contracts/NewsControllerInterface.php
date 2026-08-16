<?php

declare(strict_types=1);

namespace Topics\News\Contracts;

interface NewsControllerInterface
{
    public function main(mixed $new_topic): void;
}
