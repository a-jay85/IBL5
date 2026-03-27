<?php

declare(strict_types=1);

namespace PlrParser;

enum PlrImportMode: string
{
    case Live = 'live';
    case Snapshot = 'snapshot';
}
