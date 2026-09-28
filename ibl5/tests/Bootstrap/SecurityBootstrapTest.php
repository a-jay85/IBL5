<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\SecurityBootstrap;
use PHPUnit\Framework\TestCase;

final class SecurityBootstrapTest extends TestCase
{
    public function testBootDoesNotStartOutputBuffer(): void
    {
        $level = ob_get_level();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';

        $container = self::createStub(\Bootstrap\Contracts\ContainerInterface::class);
        $bootstrap = new SecurityBootstrap();

        // redirectFacebookBot calls exit() on FB UA — use a normal UA
        // boot() should NOT add any output buffers
        $bootstrap->boot($container);

        self::assertSame($level, ob_get_level());
    }

    public function testIncludeSafeBlocksPathTraversal(): void
    {
        // Create a temp file to test inclusion
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/safe-test-file.php';
        file_put_contents($tempFile, '<?php $GLOBALS["include_secure_test"] = true;');

        // Path traversal attempt should be sanitized
        SecurityBootstrap::includeSafe('../../../../../../' . $tempFile);

        // The traversal should have been stripped, so the file won't be found
        // (basename stripping makes it look for just 'safe-test-file.php' in CWD)
        self::assertArrayNotHasKey('include_secure_test', $GLOBALS);

        unlink($tempFile);
    }

    public function testIncludeSafeBlocksNonPhpExtension(): void
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/malicious.sh';
        file_put_contents($tempFile, '<?php $GLOBALS["ext_block_test"] = true;');
        $originalCwd = getcwd();
        chdir($tempDir);

        try {
            SecurityBootstrap::includeSafe('malicious.sh');
            self::assertArrayNotHasKey('ext_block_test', $GLOBALS, 'Non-.php extension must be blocked by includeSafe');
        } finally {
            chdir((string) $originalCwd);
            unlink($tempFile);
        }
    }

    public function testIncludeSafeBlocksSpecialCharactersInFilename(): void
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/file;rm -rf.php';
        file_put_contents($tempFile, '<?php $GLOBALS["special_chars_block_test"] = true;');
        $originalCwd = getcwd();
        chdir($tempDir);

        try {
            SecurityBootstrap::includeSafe('file;rm -rf.php');
            self::assertArrayNotHasKey('special_chars_block_test', $GLOBALS, 'Filenames with special chars must be blocked by includeSafe');
        } finally {
            chdir((string) $originalCwd);
            unlink($tempFile);
        }
    }

    public function testIncludeSafeAllowsValidPhpFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $originalCwd = getcwd();

        // Create a valid PHP file in the temp dir
        $tempFile = $tempDir . '/valid-include-test.php';
        file_put_contents($tempFile, '<?php $GLOBALS["valid_include_test"] = "included";');

        // Change to temp dir so the relative path resolves
        chdir($tempDir);

        SecurityBootstrap::includeSafe('valid-include-test.php');

        self::assertSame('included', $GLOBALS['valid_include_test'] ?? null);

        // Cleanup
        unset($GLOBALS['valid_include_test']);
        unlink($tempFile);
        if ($originalCwd !== false) {
            chdir($originalCwd);
        }
    }

    public function testIncludeSafeHandlesEmptyString(): void
    {
        $level = ob_get_level();
        SecurityBootstrap::includeSafe('');
        self::assertSame($level, ob_get_level(), 'includeSafe with empty string must not alter output buffer state');
    }

    public function testIncludeSafeStripsNullBytes(): void
    {
        // PHP 8 throws ValueError from file_exists() when a path contains a null byte.
        // The str_replace("\0", '', $dir) guard in includeSafe must strip the null byte
        // before the file_exists() call, or the call would throw.
        // If the guard is removed, this test fails because the ValueError propagates.
        $level = ob_get_level();
        SecurityBootstrap::includeSafe("subdir\0/test.php");
        self::assertSame($level, ob_get_level(), 'includeSafe with null-byte path must not alter output buffer state');
    }
}
