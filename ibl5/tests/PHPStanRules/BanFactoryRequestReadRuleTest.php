<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanFactoryRequestReadRule;

/**
 * Rule tests for the factory request-read and identity-parameter ban (ADR-0171).
 *
 * What each check guards against:
 * - Pass-through: the zero-arg ModuleServices::request() accessor stays legal, so a rule
 *   without the receiver-type guard would flag it.
 * - Corpus: ApiKeysFactory::controller() takes host and https as arguments and reads no
 *   request field itself; reading them there again is what the registered rule reports.
 * - Registration: naming PHPStanRules\BanFactoryRequestReadRuleX in phpstan.neon aborts
 *   PHPStan at container build.
 * - Security surface: a factory parameter named $sessionHost matches the identity pattern
 *   that ModuleFactorySecuritySurfaceTest detects.
 *
 * @extends RuleTestCase<BanFactoryRequestReadRule>
 */
final class BanFactoryRequestReadRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanFactoryRequestReadRule();
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/Fixtures/phpstan-fixtures.neon'];
    }

    private static function readMessage(string $method): string
    {
        return 'Module factory reads a request field via Http\HttpRequest::' . $method
            . '(). Read it in the module index.php and pass the value in (ADR-0171).';
    }

    private static function identityMessage(string $name): string
    {
        return 'Module factory parameter $' . $name
            . ' looks like actor identity. Resolve identity in index.php and pass it to the controller or service (ADR-0171).';
    }

    public function testFlagsRequestFieldReadsInFactory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Module/Factories/RequestReadFactory.php'],
            [
                [self::readMessage('server'), 15],
                [self::readMessage('request'), 20],
                [self::readMessage('get'), 26],
                [self::readMessage('allPost'), 31],
            ],
        );
    }

    public function testAllowsRequestPassThroughInFactory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Module/Factories/RequestPassThroughFactory.php'],
            [],
        );
    }

    public function testFlagsIdentityNamedParametersInFactory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Module/Factories/IdentityParamFactory.php'],
            [
                [self::identityMessage('loggedInTeamId'), 9],
                [self::identityMessage('sessionToken'), 14],
            ],
        );
    }

    public function testIgnoresRequestReadsOutsideFactoriesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/RequestReadOutsideFactory.php'],
            [],
        );
    }

    public function testIdentityPatternMatchesSecuritySurfaceTest(): void
    {
        $securityPattern = (new \ReflectionClassConstant(
            \Tests\Security\ModuleFactorySecuritySurfaceTest::class,
            'IDENTITY_PARAMETER_PATTERN',
        ))->getValue();

        self::assertSame($securityPattern, BanFactoryRequestReadRule::IDENTITY_PARAMETER_PATTERN);
    }
}
