<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Exit_>
 */
final class BanDieExitInProductionRule implements Rule
{
    private const ALLOWED_FILE_SUFFIXES = ['Bootstrap.php'];

    private const ALLOWED_FILES = [
        'LegacyFunctions.php',
        'HtmxHelper.php',
    ];

    public function getNodeType(): string
    {
        return Exit_::class;
    }

    /**
     * @param Exit_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
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
                'die/exit is banned outside Bootstrap, LegacyFunctions.php, and HtmxHelper.php. '
                . 'Process termination prevents response logging, footer rendering, and unit testing. '
                . 'Return a value, throw a typed exception, or use a Responder.'
            )
                ->identifier('ibl.dieExit')
                ->build(),
        ];
    }
}
