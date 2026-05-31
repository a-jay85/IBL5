<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans variable interpolation inside SQL string literals.
 *
 * An InterpolatedString node ("... $var ..." / "... {$expr} ...") that also
 * contains SQL keywords is an injection-shaped construct: the interpolated value
 * is spliced directly into the query text. Use bound parameters (prepared
 * statements) for values, and validated-enum/match() lookups for identifiers
 * (table/column names) instead.
 *
 * Plain concatenation ("..." . CONST) and parameterized literals ("WHERE x = ?")
 * are NOT InterpolatedString nodes, so they are correctly ignored.
 *
 * @implements Rule<InterpolatedString>
 */
final class BanSqlStringInterpolationRule implements Rule
{
    /**
     * Matches a literal that *begins* (after leading whitespace) with a SQL DML verb
     * or a SQL clause keyword. Anchoring at the start is what distinguishes a real
     * query/fragment from prose that merely mentions a keyword ("...offer from the
     * team...") — prose strings that open with an interpolated value do not begin
     * with a SQL token, so they are not matched.
     */
    private const SQL_STATEMENT_PATTERN = '/^\s*(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|WHERE|ORDER\s+BY|GROUP\s+BY|HAVING|LEFT\s+JOIN|RIGHT\s+JOIN|INNER\s+JOIN)\b/i';

    public function getNodeType(): string
    {
        return InterpolatedString::class;
    }

    /**
     * @param InterpolatedString $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $literal = '';
        foreach ($node->parts as $part) {
            if ($part instanceof InterpolatedStringPart) {
                $literal .= $part->value;
            }
        }

        if (preg_match(self::SQL_STATEMENT_PATTERN, $literal) !== 1) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'SQL string uses variable interpolation. Splicing a variable into query '
                . 'text risks SQL injection. Use bound parameters (?) for values, and a '
                . 'validated allowlist/match() for identifiers (table/column names).'
            )
                ->identifier('ibl.sqlStringInterpolation')
                ->build(),
        ];
    }
}
