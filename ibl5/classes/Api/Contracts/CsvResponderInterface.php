<?php

declare(strict_types=1);

namespace Api\Contracts;

interface CsvResponderInterface
{
    /**
     * @param list<list<string>> $rows First row is the header row
     */
    public function send(array $rows, string $filename): void;

    /**
     * @param resource $handle Writable stream
     * @param list<list<string>> $rows
     */
    public function renderRows(mixed $handle, array $rows): void;
}
