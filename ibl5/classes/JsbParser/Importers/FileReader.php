<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\JsbImportResult;

class FileReader
{
    /**
     * @param callable(string): JsbImportResult $processor
     */
    public static function readOrFail(string $filePath, string $label, callable $processor): JsbImportResult
    {
        if (!file_exists($filePath)) {
            $result = new JsbImportResult();
            $result->addError($label . ' file not found: ' . $filePath);
            return $result;
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            $result = new JsbImportResult();
            $result->addError('Failed to read ' . $label . ' file: ' . $filePath);
            return $result;
        }

        return $processor($data);
    }
}
