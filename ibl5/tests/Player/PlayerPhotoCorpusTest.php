<?php

declare(strict_types=1);

namespace Tests\Player;

use Player\PlayerImageHelper;
use PHPUnit\Framework\TestCase;

/**
 * Guards the player photo corpus: PlayerImageHelper::getImageUrl() only serves
 * <pid>.jpg or <pid>.png, so a photo in any other format with no .jpg/.png twin
 * is unreachable.
 *
 * Regression: pid 1742 shipped as 1742.jpeg and rendered broken (backlog#1317).
 * The golden .jpg URL assertions stay in ibl5/tests/Player/PlayerImageHelperTest.php.
 */
final class PlayerPhotoCorpusTest extends TestCase
{
    /** Extensions PlayerImageHelper::getImageUrl() can serve. */
    private const SERVED_EXTENSIONS = ['jpg', 'png'];

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ibl-photo-corpus-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $entries = scandir($this->tmpDir);
        if ($entries !== false) {
            foreach ($entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path = $this->tmpDir . '/' . $name;
                if (is_dir($path)) {
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($this->tmpDir);
    }

    /**
     * @return list<string> sorted basenames of non-.jpg/.png files lacking a .jpg or .png twin
     */
    private static function findUnservablePhotos(string $dir): array
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }

        $orphans = [];
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || !is_file($dir . '/' . $name)) {
                continue;
            }
            if (in_array(pathinfo($name, PATHINFO_EXTENSION), self::SERVED_EXTENSIONS, true)) {
                continue;
            }
            // is_file(), not a listing lookup: the corpus tracks both 86.JPG and
            // 86.jpg, and only one lands on a case-insensitive checkout.
            $base = $dir . '/' . pathinfo($name, PATHINFO_FILENAME);
            if (!is_file($base . '.jpg') && !is_file($base . '.png')) {
                $orphans[] = $name;
            }
        }

        sort($orphans);

        return $orphans;
    }

    public function testEveryPlayerPhotoIsServable(): void
    {
        $dir = dirname(__DIR__, 2) . '/images/player';
        self::assertDirectoryExists($dir);

        $jpgCount = 0;
        $entries = scandir($dir);
        self::assertIsArray($entries);
        foreach ($entries as $name) {
            if (pathinfo($name, PATHINFO_EXTENSION) === 'jpg') {
                $jpgCount++;
            }
        }
        self::assertGreaterThan(1000, $jpgCount, 'Photo corpus looks empty or the path is wrong: ' . $dir);

        $orphans = self::findUnservablePhotos($dir);
        self::assertSame(
            [],
            $orphans,
            'Player photos with no .jpg/.png twin (PlayerImageHelper::getImageUrl() only serves <pid>.jpg or <pid>.png): ' . implode(', ', $orphans)
        );
    }

    public function testPlayer1742ImageUrlPointsAtAnExistingFile(): void
    {
        $url = PlayerImageHelper::getImageUrl(1742);
        self::assertSame('./images/player/1742.jpg', $url);
        self::assertFileExists(dirname(__DIR__, 2) . '/' . substr($url, 2));
    }

    public function testOrphanDetectorFlagsPhotoWithoutJpgOrPngTwin(): void
    {
        touch($this->tmpDir . '/10.png');
        touch($this->tmpDir . '/10.jpg');
        touch($this->tmpDir . '/20.jpeg');
        touch($this->tmpDir . '/30.jpg');
        touch($this->tmpDir . '/40.bmp');
        touch($this->tmpDir . '/60.png');
        mkdir($this->tmpDir . '/50.jpeg');

        self::assertSame(['20.jpeg', '40.bmp'], self::findUnservablePhotos($this->tmpDir));
    }

    public function testOrphanDetectorReturnsEmptyWhenEveryPhotoHasServableTwin(): void
    {
        touch($this->tmpDir . '/10.png');
        touch($this->tmpDir . '/10.jpg');
        touch($this->tmpDir . '/20.jpeg');
        touch($this->tmpDir . '/20.jpg');
        touch($this->tmpDir . '/30.jpg');
        touch($this->tmpDir . '/40.bmp');
        touch($this->tmpDir . '/40.png');

        self::assertSame([], self::findUnservablePhotos($this->tmpDir));
    }
}
