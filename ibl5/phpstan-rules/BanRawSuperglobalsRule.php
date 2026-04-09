<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans direct access to `$_GET`, `$_POST`, `$_REQUEST`, and `$_COOKIE` outside
 * the sanctioned input-boundary layer (Controllers, ApiHandlers, and CsrfGuard).
 *
 * Services, Repositories, Views, Calculators, and other inner-layer classes
 * must receive validated, typed inputs from a Controller or ApiHandler rather
 * than reading superglobals directly. This keeps the validation surface area
 * small and enforces the Repository-Service-View architecture documented in
 * CLAUDE.md.
 *
 * @implements Rule<Variable>
 */
final class BanRawSuperglobalsRule implements Rule
{
    /** @var list<string> */
    private const BANNED_SUPERGLOBALS = [
        '_GET',
        '_POST',
        '_REQUEST',
        '_COOKIE',
    ];

    /**
     * File-basename patterns that are allowed to read superglobals.
     * The sanctioned input-boundary layer: Controllers, ApiHandlers, and CsrfGuard.
     *
     * @var list<string>
     */
    private const ALLOWED_FILE_SUFFIXES = [
        'Controller.php',
        'ApiHandler.php',
    ];

    /** @var list<string> */
    private const ALLOWED_FILES = [
        'CsrfGuard.php',
    ];

    public function getNodeType(): string
    {
        return Variable::class;
    }

    /**
     * @param Variable $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!is_string($node->name)) {
            return [];
        }

        if (!in_array($node->name, self::BANNED_SUPERGLOBALS, true)) {
            return [];
        }

        $file = $scope->getFile();

        // Only enforce in classes/
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        // Allow in sanctioned input-boundary files
        foreach (self::ALLOWED_FILE_SUFFIXES as $suffix) {
            if (str_ends_with($file, $suffix)) {
                return [];
            }
        }
        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Direct $' . $node->name . ' access is banned outside Controllers, '
                . 'ApiHandlers, and Utilities\CsrfGuard. Accept typed inputs as parameters '
                . 'from a Controller/ApiHandler instead.'
            )
                ->identifier('ibl.rawSuperglobal')
                ->build(),
        ];
    }
}
