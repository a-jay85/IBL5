<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\WikipediaLinkPolicy;

final class WikipediaLinkPolicyTest extends TestCase
{
    /** Source tokens that would let the validator read ambient state or do I/O. */
    private const FORBIDDEN_SOURCE_TOKENS = [
        '$_SESSION', '$_GET', '$_POST', '$_COOKIE', '$_SERVER', '$GLOBALS', 'global ',
        'define(', 'constant(', 'getenv(', 'file_get_contents(', 'fopen(', 'curl_',
        'mysqli', '->query(', 'echo', '$db', '$loggedInTeamID',
    ];

    public function testAcceptsProseWithNoLinks(): void
    {
        $prose = "**A 100 @ B 99**\n<@123> · <@456>\n\nA close one that went to the wire.";

        $this->assertNull(WikipediaLinkPolicy::violation($prose));
    }

    public function testAcceptsWikipediaLinkInAngleBrackets(): void
    {
        $prose = 'It felt like [the Miracle on Ice](<https://en.wikipedia.org/wiki/Miracle_on_Ice>) all over again.';

        $this->assertNull(WikipediaLinkPolicy::violation($prose));
    }

    public function testAcceptsWikipediaPathWithParenthesesAndPercentEncoding(): void
    {
        $this->assertNull(WikipediaLinkPolicy::violation(
            'A [Rocky](<https://en.wikipedia.org/wiki/Rocky_(1976_film)>) comeback.'
        ));
        $this->assertNull(WikipediaLinkPolicy::violation(
            'Footwork out of [Pelé](<https://en.wikipedia.org/wiki/Pel%C3%A9>) highlights.'
        ));
    }

    #[DataProvider('adversarialLinkProvider')]
    public function testRejectsAdversarialLink(string $prose): void
    {
        $this->assertIsString(WikipediaLinkPolicy::violation($prose));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adversarialLinkProvider(): array
    {
        $good = '[Miracle](<https://en.wikipedia.org/wiki/Miracle_on_Ice>)';

        return [
            'other host' => ['[x](<https://example.com/wiki/X>)'],
            'lookalike suffix host' => ['[x](<https://en.wikipedia.org.evil.com/wiki/X>)'],
            'userinfo at-sign' => ['[x](<https://en.wikipedia.org@evil.com/wiki/X>)'],
            'other wikipedia language' => ['[x](<https://de.wikipedia.org/wiki/X>)'],
            'mobile wikipedia host' => ['[x](<https://en.m.wikipedia.org/wiki/X>)'],
            'non-wiki path' => ['[x](<https://en.wikipedia.org/w/index.php?title=X>)'],
            'http scheme' => ['[x](<http://en.wikipedia.org/wiki/X>)'],
            'javascript scheme' => ['[x](<javascript:alert(1)>)'],
            'missing angle brackets' => ['[x](https://en.wikipedia.org/wiki/X)'],
            'close-angle inside url' => ['[x](<https://en.wikipedia.org/wiki/X>evil>)'],
            'space inside url' => ['[x](<https://en.wikipedia.org/wiki/X Y>)'],
            'newline inside url' => ["[x](<https://en.wikipedia.org/wiki/X\nY>)"],
            'scheme-relative' => ['[x](<//en.wikipedia.org/wiki/X>)'],
            'empty link text' => ['[](<https://en.wikipedia.org/wiki/X>)'],
            'bare https url' => ['see https://en.wikipedia.org/wiki/X for more'],
            'bare uppercase scheme' => ['see HTTPS://evil.com'],
            'bare angle url' => ['see <https://evil.com>'],
            'team page url written by generator' => ['[Celtics](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=2>)'],
            'one good one bad' => [$good . ' then [y](<https://evil.com>)'],
            'data scheme' => ['[x](<data:text/html,<script>alert(1)</script>>)'],
            'nested brackets' => ['[[x]](<https://en.wikipedia.org/wiki/X>)'],
            'newline in link text' => ["[x\ny](<https://en.wikipedia.org/wiki/X>)"],
        ];
    }

    public function testClassHoldsNoStateOrActorIdentity(): void
    {
        $class = new \ReflectionClass(WikipediaLinkPolicy::class);

        $this->assertSame([], $this->stateViolations($class, $this->sourceOf($class)));

        $publicMethods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            $class->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        $this->assertSame(['violation'], $publicMethods);

        $returnType = $class->getMethod('violation')->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    public function testStateFreeInvariantDetectsForbiddenProperty(): void
    {
        $planted = new class {
            private ?\mysqli $db = null;

            public static function check(int $teamId): bool
            {
                return $teamId > 0;
            }

            public function hasDb(): bool
            {
                return $this->db !== null;
            }
        };
        $source = '<?php final class Planted { private $db; public static function check() { return $_SESSION; } }';

        $violations = $this->stateViolations(new \ReflectionClass($planted), $source);

        $this->assertContains('declares property $db', $violations);
        $this->assertContains('check() takes a non-string parameter $teamId', $violations);
        $this->assertContains('source contains $_SESSION', $violations);
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function sourceOf(\ReflectionClass $class): string
    {
        $file = $class->getFileName();
        $this->assertIsString($file);
        // phpunit-hygiene-allow: the security invariant is the validator source's shape (no ambient-state calls)
        $source = file_get_contents($file);
        $this->assertIsString($source);

        return $source;
    }

    /**
     * Every way the class could hold state, take an actor, or reach ambient state.
     *
     * @param \ReflectionClass<object> $class
     * @return list<string>
     */
    private function stateViolations(\ReflectionClass $class, string $source): array
    {
        $violations = [];

        foreach ($class->getProperties() as $property) {
            $violations[] = 'declares property $' . $property->getName();
        }

        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            $violations[] = 'declares a constructor with ' . $constructor->getNumberOfParameters() . ' parameters';
        }

        foreach ($class->getMethods() as $method) {
            if ($method->isPublic() && !$method->isStatic()) {
                $violations[] = $method->getName() . '() is a non-static public method';
            }
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                if (!$type instanceof \ReflectionNamedType || $type->getName() !== 'string') {
                    $violations[] = $method->getName() . '() takes a non-string parameter $' . $parameter->getName();
                }
            }
        }

        foreach (self::FORBIDDEN_SOURCE_TOKENS as $token) {
            if (str_contains($source, $token)) {
                $violations[] = 'source contains ' . $token;
            }
        }

        return $violations;
    }
}
