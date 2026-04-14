<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

class LogoResolver
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $docRoot = is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '';
        $this->basePath = $basePath ?? ($docRoot . '/ibl5/');
    }

    public function resolve(int $franchiseId, string $teamName): string
    {
        $eraSpecific = 'images/logo/' . $franchiseId . '(' . $teamName . ').jpg';
        if (file_exists($this->basePath . $eraSpecific)) {
            return $eraSpecific;
        }

        $fallback = 'images/logo/' . $franchiseId . '.jpg';
        if (file_exists($this->basePath . $fallback)) {
            return $fallback;
        }

        return 'images/logo/new' . $franchiseId . '.png';
    }
}
