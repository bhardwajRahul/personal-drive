<?php

namespace Tests\Unit\Services;

use App\Models\LocalFile;
use App\Services\FileDeleteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileDeleteServiceTest extends TestCase
{
    use RefreshDatabase;


    protected FileDeleteService $fileDeleteService;
    protected string $tempDir;
    protected string $tempFile;

    public function testIsDeletableDirectory()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('__get')->willReturnMap(
            [
            ['is_dir', 1],
            ['private_path', $this->tempDir],
            ]
        );

        $result = $this->fileDeleteService->isDeletableDirectory($file, sys_get_temp_dir());
        $this->assertTrue($result);
    }

    public function testIsDeletableDirectoryFalseWhenNotDirectory()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('__get')->willReturnMap(
            [
            ['is_dir', 0],
            ['private_path', $this->tempDir],
            ]
        );

        $result = $this->fileDeleteService->isDeletableDirectory($file, sys_get_temp_dir());
        $this->assertFalse($result);
    }

    public function testIsDeletableFile()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('__get')->willReturnMap(
            [
            ['is_dir', 0],
            ['private_path', $this->tempDir],
            ]
        );

        $result = $this->fileDeleteService->isDeletableFile($file);
        $this->assertTrue($result);
    }

    public function testIsDeletableFileFalseWhenDirectory()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('__get')->willReturnMap(
            [
            ['is_dir', 1],
            ['private_path', $this->tempDir],
            ]
        );

        $result = $this->fileDeleteService->isDeletableFile($file);
        $this->assertFalse($result);
    }

    public function testIsDirSubDirOfStorage()
    {
        $this->assertTrue(
            $this->fileDeleteService->isDirSubDirOfStorage(
                $this->tempDir,
                sys_get_temp_dir()
            )
        );
    }

    public function testIsDirSubDirOfStorageFalseWhenNotUnderStorage()
    {
        $this->assertFalse(
            $this->fileDeleteService->isDirSubDirOfStorage(
                sys_get_temp_dir(),
                $this->tempDir
            )
        );
    }

    public function testIsDirSubDirOfStorageRejectsPrefixSibling()
    {
        $suffix = bin2hex(random_bytes(4));
        $storage = sys_get_temp_dir() . DS . 'personal-drive-' . $suffix;
        $prefixSibling = $storage . '-backup';
        mkdir($storage);
        mkdir($prefixSibling);

        try {
            $this->assertFalse(
                $this->fileDeleteService->isDirSubDirOfStorage($prefixSibling, $storage)
            );
        } finally {
            rmdir($prefixSibling);
            rmdir($storage);
        }
    }

    public function testIsDirSubDirOfStorageRejectsCanonicalPathOutsideStorage()
    {
        $base = sys_get_temp_dir() . DS . 'personal-drive-' . bin2hex(random_bytes(4));
        $storage = $base . DS . 'storage';
        $outside = $base . DS . 'outside';
        mkdir($base);
        mkdir($storage);
        mkdir($outside);

        try {
            $this->assertFalse(
                $this->fileDeleteService->isDirSubDirOfStorage(
                    $storage . DS . '..' . DS . 'outside',
                    $storage
                )
            );
        } finally {
            rmdir($outside);
            rmdir($storage);
            rmdir($base);
        }
    }

    public function testDeleteFilesDeletesFile()
    {
        $file1 = LocalFile::factory()->create(
            [
            'private_path' => $this->tempDir,
            'filename' => $this->tempFile,
            'is_dir' => 0,
            ]
        );

        $builder = LocalFile::whereIn('id', [$file1->id]);
        $this->assertFileExists($this->tempDir . $this->tempFile);

        $this->fileDeleteService->deleteFiles($builder, sys_get_temp_dir());
        $this->assertFileDoesNotExist($this->tempDir . $this->tempFile);
    }

    public function testDeleteFilesDeletesFilesAndDirectories()
    {
        $tempSubDir = 'testSubDir';
        @mkdir($this->tempDir . $tempSubDir);
        $dir = LocalFile::factory()->create(
            [
            'private_path' => $this->tempDir,
            'filename' => $tempSubDir,
            'is_dir' => 1,
            ]
        );

        $this->assertFileExists($this->tempDir . $this->tempFile);

        $builder = LocalFile::whereIn('id', [$dir->id]);
        $this->fileDeleteService->deleteFiles($builder, sys_get_temp_dir());

        $this->assertDirectoryDoesNotExist($dir);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileDeleteService = new FileDeleteService();

        // create temp directory and file
        $this->tempDir = sys_get_temp_dir() . '/testDir/';
        @mkdir($this->tempDir);
        $this->tempFile = 'test.txt';
        file_put_contents($this->tempDir . $this->tempFile, 'content');
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . $this->tempFile);
        @rmdir($this->tempDir);

        parent::tearDown();
    }
}
