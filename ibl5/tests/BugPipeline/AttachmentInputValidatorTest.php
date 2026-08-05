<?php

declare(strict_types=1);

namespace Tests\BugPipeline;

use BugPipeline\AttachmentInputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttachmentInputValidatorTest extends TestCase
{
    private AttachmentInputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AttachmentInputValidator();
        // Pin the cache root so local_path cases are deterministic regardless of the host env.
        putenv('BUG_PIPELINE_ATTACHMENT_CACHE_DIR=/tmp/bp-cache');
        putenv('BUG_PIPELINE_ATTACHMENT_HOSTS'); // clear any override → defaults
    }

    protected function tearDown(): void
    {
        putenv('BUG_PIPELINE_ATTACHMENT_CACHE_DIR');
        putenv('BUG_PIPELINE_ATTACHMENT_HOSTS');
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function entry(array $overrides = []): array
    {
        return array_merge([
            'attachment_id' => '700000000000000001',
            'original_url'  => 'https://cdn.discordapp.com/attachments/1/2/shot.png',
            'local_path'    => null,
            'filename'      => 'shot.png',
            'content_type'  => 'image/png',
            'file_size'     => 12345,
        ], $overrides);
    }

    public function testValidEntrySurvivesUnchanged(): void
    {
        $reject = 'sentinel';
        $out = $this->validator->validateAll([$this->entry()], $reject);

        $this->assertCount(1, $out);
        $this->assertSame($this->entry(), $out[0]);
        $this->assertNull($reject, 'no rejections ⇒ reject log is null');
    }

    public function testEmptyArrayReturnsEmptyAndDoesNotTripNotAListBranch(): void
    {
        $reject = 'sentinel';
        $out = $this->validator->validateAll([], $reject);

        $this->assertSame([], $out);
        $this->assertNull($reject);
    }

    public function testPreservesNineteenDigitSnowflakeAsString(): void
    {
        $out = $this->validator->validateAll([$this->entry(['attachment_id' => '1234567890123456789'])]);
        $this->assertSame('1234567890123456789', $out[0]['attachment_id']);
    }

    public function testAcceptsWellFormedLocalPath(): void
    {
        $path = '/tmp/bp-cache/300000000000000003-700000000000000001.png';
        $out = $this->validator->validateAll([$this->entry(['local_path' => $path])]);
        $this->assertSame($path, $out[0]['local_path']);
    }

    public function testTrailingSlashOnCacheRootStillMatches(): void
    {
        putenv('BUG_PIPELINE_ATTACHMENT_CACHE_DIR=/tmp/bp-cache/'); // note trailing slash
        $path = '/tmp/bp-cache/300000000000000003-700000000000000001.png';
        $out = $this->validator->validateAll([$this->entry(['local_path' => $path])]);
        $this->assertCount(1, $out, 'rtrim on the root must not break a valid path');
    }

    public function testHonorsHostAllowlistOverride(): void
    {
        putenv('BUG_PIPELINE_ATTACHMENT_HOSTS=media.example.com');
        $out = $this->validator->validateAll([
            $this->entry(['original_url' => 'https://media.example.com/a.png']),
            $this->entry(['original_url' => 'https://cdn.discordapp.com/attachments/1/2/shot.png']), // default now blocked
        ]);
        $this->assertCount(1, $out);
        $this->assertSame('https://media.example.com/a.png', $out[0]['original_url']);
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('rejectableEntries')]
    public function testRejectsMalformedField(array $override): void
    {
        $reject = null;
        $out = $this->validator->validateAll([$this->entry($override)], $reject);

        $this->assertSame([], $out, 'malformed entry must be dropped');
        $this->assertNotNull($reject, 'a drop must be logged');
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function rejectableEntries(): array
    {
        return [
            'attachment_id non-numeric'      => [['attachment_id' => '12a']],
            'attachment_id traversal'        => [['attachment_id' => '../../etc/passwd']],
            'attachment_id empty'            => [['attachment_id' => '']],
            'attachment_id 21 digits'        => [['attachment_id' => '123456789012345678901']],
            'attachment_id int not string'   => [['attachment_id' => 700000000000000001]],
            'url http not https'             => [['original_url' => 'http://cdn.discordapp.com/a.png']],
            'url disallowed host'            => [['original_url' => 'https://evil.example.com/a.png']],
            'url lookalike host'             => [['original_url' => 'https://cdn.discordapp.com.evil.com/a.png']],
            'url too long'                   => [['original_url' => 'https://cdn.discordapp.com/' . str_repeat('a', 2100)]],
            // local_path lives in degradableLocalPaths() below — it degrades, it does not drop.
            'filename empty'                 => [['filename' => '']],
            'filename too long'              => [['filename' => str_repeat('a', 256)]],
            'filename control char'          => [['filename' => "shot\x00.png"]],
            'content_type empty'             => [['content_type' => '']],
            'content_type not mime'          => [['content_type' => 'notamimetype']],
            'content_type too long'          => [['content_type' => 'image/' . str_repeat('x', 100)]],
            'file_size string'               => [['file_size' => '12']],
            'file_size negative'             => [['file_size' => -1]],
            'file_size over cap'             => [['file_size' => 10485761]],
        ];
    }

    /**
     * local_path is the ONE field that degrades instead of dropping: null is already its
     * failed-download state, so nulling a bad path keeps an entry the rest of the pipeline
     * handles natively rather than discarding an independently-valid original_url + filename
     * + content_type. The rejected path itself must never reach the returned record.
     *
     * @param mixed $badPath
     */
    #[DataProvider('degradableLocalPaths')]
    public function testMalformedLocalPathDegradesToNullAndIsLogged(mixed $badPath): void
    {
        $reject = null;
        $out = $this->validator->validateAll([$this->entry(['local_path' => $badPath])], $reject);

        $this->assertCount(1, $out, 'the entry survives — only local_path is lost');
        $this->assertNull($out[0]['local_path'], 'the malformed path must not reach the DB');
        // `?? ''` rather than a preceding assertNotNull: the empty string cannot contain
        // 'local_path', so this single assertion proves both non-null AND the right reason.
        $this->assertStringContainsString(
            'local_path',
            $reject ?? '',
            'a silent degrade is the drift an operator needs to see'
        );
        // Every other field is untouched.
        $this->assertSame('700000000000000001', $out[0]['attachment_id']);
        $this->assertSame('shot.png', $out[0]['filename']);
    }

    /** @return array<string, array{0: mixed}> */
    public static function degradableLocalPaths(): array
    {
        return [
            'traversal'      => ['/tmp/bp-cache/../../../etc/passwd'],
            'wrong root'     => ['/etc/1-2.png'],
            'bad extension'  => ['/tmp/bp-cache/1-2.exe'],
            'not a string'   => [42],
            'array'          => [['/tmp/bp-cache/1-2.png']],
        ];
    }

    public function testNullLocalPathAndNullFileSizeAreAccepted(): void
    {
        $out = $this->validator->validateAll([$this->entry(['local_path' => null, 'file_size' => null])]);
        $this->assertCount(1, $out);
        $this->assertNull($out[0]['local_path']);
        $this->assertNull($out[0]['file_size']);
    }

    public function testNonArrayEntryIsDropped(): void
    {
        $reject = null;
        $out = $this->validator->validateAll(['not-an-object', $this->entry()], $reject);
        $this->assertCount(1, $out);
        $this->assertNotNull($reject);
    }

    public function testCapsAtTenSurvivorsNotPositions(): void
    {
        // Two leading junk entries then twelve valid: survivors cap at 10, and the junk
        // must NOT consume slots that would have held real attachments.
        $raw = [
            $this->entry(['original_url' => 'https://evil.example.com/x.png']),
            $this->entry(['attachment_id' => 'bad']),
        ];
        for ($i = 0; $i < 12; $i++) {
            $raw[] = $this->entry(['attachment_id' => (string) (700000000000000000 + $i)]);
        }
        $reject = null;
        $out = $this->validator->validateAll($raw, $reject);

        $this->assertCount(AttachmentInputValidator::MAX_ATTACHMENTS, $out);
        $this->assertNotNull($reject);
    }

    public function testNonListPayloadDropsEverything(): void
    {
        $reject = null;
        // A JSON object decodes to a non-list assoc array.
        $out = $this->validator->validateAll(['a' => $this->entry()], $reject);
        $this->assertSame([], $out);
        $this->assertNotNull($reject);
    }
}
