<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

use PlrParser\PlrParseResult;

/**
 * Service interface for PLR file processing.
 */
interface PlrParserServiceInterface
{
    /**
     * Process a .plr file — parse all players, upsert into DB, assign team names.
     *
     * @param string $filePath Absolute path to the .plr file
     * @return PlrParseResult Result summary
     */
    public function processPlrFile(string $filePath): PlrParseResult;
}
