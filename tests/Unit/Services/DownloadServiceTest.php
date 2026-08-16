<?php

namespace Tests\Unit\Services;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Helpers\DownloadHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;

class DownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $downloadService;

    public function testGenerateDownloadPathSingleFile()
    {
        $file = $this->createMock(LocalFile::class);
        $file->is_dir = false;
        $file->method('getPrivatePathNameForFile')->willReturn('/path/to/file');

        $localFiles = new Collection([$file]);

        $result = $this->downloadService->generateDownloadPath($localFiles);

        $this->assertEquals('/path/to/file', $result);
    }


    public function testZipNamesAreUniqueAcrossCalls()
    {
        $dir = sys_get_temp_dir() . '/pd_test_' . uniqid();
        mkdir($dir . '/sub', 0777, true);
        file_put_contents($dir . '/sub/a.txt', 'a');

        $file = $this->createMock(LocalFile::class);
        $file->method('getPrivatePathNameForFile')->willReturn($dir);
        $file->method('__get')->with('is_dir')->willReturn(true);

        $localFiles = new Collection([$file]);
        $first = $this->downloadService->createZipFile($localFiles);
        $second = $this->downloadService->createZipFile($localFiles);

        $this->assertNotSame($first, $second);
        $this->assertFileExists($first);
        preg_match('#personal_drive_([^_]+)_\d{4}_\d{2}_\d{2}\.zip$#', $first, $m);
        $this->assertGreaterThanOrEqual(10, strlen($m[1] ?? ''));

        @unlink($first);
        @unlink($second);
        @unlink($dir . '/sub/a.txt');
        @rmdir($dir . '/sub');
        @rmdir($dir);
    }

    public function testIsSingleFile()
    {
        $file = $this->createMock(LocalFile::class);
        $file->is_dir = false;

        $localFiles = new Collection([$file]);

        $result = $this->downloadService->isSingleFile($localFiles);

        $this->assertTrue($result);
    }

    public function testIsNotSingleFile()
    {
        $file1 = $this->createMock(LocalFile::class);
        $file1->is_dir = false;

        $file2 = $this->createMock(LocalFile::class);
        $file2->is_dir = false;

        $localFiles = new Collection([$file1, $file2]);

        $result = $this->downloadService->isSingleFile($localFiles);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloadService = new DownloadService();
    }
}
