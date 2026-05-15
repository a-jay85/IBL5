<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<String_>
 */
final class BanBareTableIdentifierRule implements Rule
{
    private const PATTERN = '/(?<![`\'"])\b(FROM|JOIN|UPDATE|INTO|DELETE\s+FROM)\s+(ibl_[a-z_]+)\b(?!`)/i';

    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $value = $node->value;

        if (preg_match_all(self::PATTERN, $value, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $errors = [];

        foreach ($matches as $m) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                "Table identifier '%s' must be wrapped in backticks for PHPStan rename safety.",
                $m[2],
            ))
                ->identifier('ibl.bareTableIdentifier')
                ->build();
        }

        return $errors;
    }
}
