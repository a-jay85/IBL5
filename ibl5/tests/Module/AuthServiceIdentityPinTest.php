<?php

declare(strict_types=1);

namespace Tests\Module;

use PHPUnit\Framework\TestCase;

/**
 * Pins the premise the Trading trade-offer scripts rest on: boot stores one
 * AuthService object in both the $authService global and the container, so a
 * controller built from ModuleServices::auth() sees the same identity source.
 */
class AuthServiceIdentityPinTest extends TestCase
{
    public function testAuthBootstrapSharesOneAuthServiceInstance(): void
    {
        // phpunit-hygiene-allow: the property is the file's shape; boot() needs a live web request to run
        $source = file_get_contents(__DIR__ . '/../../classes/Bootstrap/AuthBootstrap.php');
        $this->assertIsString($source);

        $compact = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $compact .= $token[1];
            } else {
                $compact .= $token;
            }
        }

        $this->assertStringContainsString('$GLOBALS[\'authService\']=$authService;', $compact);
        $this->assertStringContainsString('$container->set(\'authService\',$authService);', $compact);
    }
}
