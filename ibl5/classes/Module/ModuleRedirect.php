<?php

declare(strict_types=1);

namespace Module;

/**
 * Constant 302 targets for modules whose content moved into a host page.
 *
 * The target never derives from the request, so an old URL's query string is
 * dropped rather than reflected.
 */
final class ModuleRedirect
{
    /** @var array<string, string> old module name => new app-relative URL */
    public const TARGETS = [
        'PlayerExportGuide'  => 'modules.php?name=ApiKeys',
        'VotingResults'      => 'modules.php?name=Voting',
        'AllStarAppearances' => 'modules.php?name=RecordHolders&op=allstar',
    ];

    public static function targetFor(string $moduleName): ?string
    {
        return self::TARGETS[$moduleName] ?? null;
    }

    /** Sends the 302 for a retired module. Caller must `return` immediately after. */
    public static function send(string $moduleName): void
    {
        $target = self::targetFor($moduleName);
        if ($target === null) {
            return;
        }

        header('Location: ' . $target, true, 302);
    }
}
