<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\Contracts\SplitStatsRepositoryInterface;
use Team\SplitStatsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Mechanizes the security-surface discharge for the display=split allow-list helper:
 * it must read no request state, touch no database handle, and construct no collaborator.
 */
final class SplitStatsRepositoryResolveStateFreeTest extends TestCase
{
    private const FORBIDDEN_SUPERGLOBALS = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_SESSION', '$_FILES', '$_ENV', '$GLOBALS'];
    private const FORBIDDEN_THIS_MEMBERS = ['db', 'fetchAll', 'fetchOne', 'fetchRow', 'fetchColumn', 'fetchValue', 'execute', 'executeQuery', 'query', 'prepare'];
    private const FORBIDDEN_FUNCTIONS = ['filter_input', 'filter_input_array', 'getenv', 'mysqli_query', 'mysqli_prepare', 'file_get_contents'];

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    public function testInterfaceDeclaresHelperWithValidatorSignature(): void
    {
        $method = new \ReflectionMethod(SplitStatsRepositoryInterface::class, 'resolveDisplayAndSplit');
        $params = $method->getParameters();

        self::assertCount(2, $params);
        self::assertSame('display', $params[0]->getName());
        self::assertSame('rawSplit', $params[1]->getName());

        $displayType = $params[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $displayType);
        self::assertSame('string', $displayType->getName());
        self::assertFalse($displayType->allowsNull());

        $rawSplitType = $params[1]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $rawSplitType);
        self::assertSame('mixed', $rawSplitType->getName());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame('array', $returnType->getName());

        self::assertTrue((new \ReflectionClass(SplitStatsRepository::class))->implementsInterface(SplitStatsRepositoryInterface::class));
        self::assertSame(
            SplitStatsRepository::class,
            (new \ReflectionMethod(SplitStatsRepository::class, 'resolveDisplayAndSplit'))->getDeclaringClass()->getName()
        );
    }

    public function testHelperBodyReadsNoRequestStateAndTouchesNoDatabase(): void
    {
        $source = $this->helperSource();

        self::assertSame([], $this->forbiddenTokensIn($source));
        self::assertStringContainsString('getValidSplitKeys', $source);
    }

    public function testScannerFlagsSuperglobalRead(): void
    {
        self::assertSame(['$_GET'], $this->forbiddenTokensIn('return $_GET["split"] ?? $rawSplit;'));
    }

    public function testScannerFlagsDatabaseAccess(): void
    {
        self::assertSame(['$this->db'], $this->forbiddenTokensIn('return $this->db->query("SELECT 1");'));
        self::assertSame(['$this->fetchAll'], $this->forbiddenTokensIn('return $this->fetchAll("SELECT 1");'));
        self::assertSame(['new'], $this->forbiddenTokensIn('return new \Team\SplitStatsRepository($db);'));
    }

    public function testHelperIssuesNoQueryAndIgnoresRequestGlobals(): void
    {
        $db = new MockDatabase();
        $repo = new SplitStatsRepository($db);
        $_GET['split'] = 'home';
        $_POST['split'] = 'home';
        $_REQUEST['split'] = 'home';

        self::assertSame(['ratings', null], $repo->resolveDisplayAndSplit('split', 'bogus_key'));
        self::assertSame(['split', 'road'], $repo->resolveDisplayAndSplit('split', 'road'));
        self::assertSame([], $db->getExecutedQueries());
    }

    /**
     * @return list<string> every forbidden token found, in source order
     */
    private function forbiddenTokensIn(string $source): array
    {
        $tokens = token_get_all('<?php ' . $source);
        $count = count($tokens);
        $found = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            if ($id === T_VARIABLE && in_array($text, self::FORBIDDEN_SUPERGLOBALS, true)) {
                $found[] = $text;
            } elseif ($id === T_VARIABLE && $text === '$this') {
                $operator = $this->nextSignificantIndex($tokens, $i);
                if ($operator !== null
                    && is_array($tokens[$operator])
                    && in_array($tokens[$operator][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)
                ) {
                    $member = $this->nextSignificantIndex($tokens, $operator);
                    if ($member !== null
                        && is_array($tokens[$member])
                        && $tokens[$member][0] === T_STRING
                        && in_array($tokens[$member][1], self::FORBIDDEN_THIS_MEMBERS, true)
                    ) {
                        $found[] = '$this->' . $tokens[$member][1];
                    }
                }
            } elseif ($id === T_STRING && in_array($text, self::FORBIDDEN_FUNCTIONS, true)) {
                $paren = $this->nextSignificantIndex($tokens, $i);
                if ($paren !== null && $tokens[$paren] === '(') {
                    $found[] = $text;
                }
            } elseif ($id === T_NEW) {
                $found[] = 'new';
            }
        }

        return $found;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function nextSignificantIndex(array $tokens, int $from): ?int
    {
        $count = count($tokens);
        for ($i = $from + 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return $i;
        }

        return null;
    }

    private function helperSource(): string
    {
        $method = new \ReflectionMethod(SplitStatsRepository::class, 'resolveDisplayAndSplit');
        $lines = file((string) $method->getFileName());
        self::assertNotFalse($lines);

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
