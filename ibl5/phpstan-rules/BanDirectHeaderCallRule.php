<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class BanDirectHeaderCallRule implements Rule
{
    private const ALLOWED_FILE_SUFFIXES = [
        // Bootstrap classes legitimately set response headers during the request lifecycle
        // (CorsBootstrap, HeadersBootstrap, RateLimitingBootstrap, SecurityBootstrap).
        'Bootstrap.php',
        // Response emitters are the canonical home for header() (CsvResponder, JsonResponder).
        'Responder.php',
        // HTMX ApiHandlers emit HX-* / Content-Type headers; current debt, allowlisted
        // so the rule catches NEW header() in services/repos/controllers, not existing emitters.
        'ApiHandler.php',
    ];

    private const ALLOWED_FILES = [
        // Canonical HTMX redirect helper (HX-Redirect / Location).
        'HtmxHelper.php',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'header') {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

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
                'header() is banned outside Bootstrap classes, Responders, and HTMX ApiHandlers. '
                . 'Route response headers through a Responder (composed by the Controller/ApiHandler).'
            )
                ->identifier('ibl.directHeader')
                ->build(),
        ];
    }
}
