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

    public function testIsPathWithinStorage()
    {
        $this->assertTrue(
            $this->fileDeleteService->isPathWithinStorage(
                $this->tempDir,
                sys_get_temp_dir()
            )
        );
    }

    public function testIsPathWithinStorageFalseWhenNotUnderStorage()
    {
        $this->assertFalse(
            $this->fileDeleteService->isPathWithinStorage(
                sys_get_temp_dir(),
                $this->tempDir
            )
        );
    }

    public function testIsPathWithinStorageRejectsPrefixSibling()
    {
        $suffix = bin2hex(random_bytes(4));
        $storage = sys_get_temp_dir() . DS . 'personal-drive-' . $suffix;
        $prefixSibling = $storage . '-backup';
        mkdir($storage);
        mkdir($prefixSibling);

        try {
            $this->assertFalse(
                $this->fileDeleteService->isPathWithinStorage($prefixSibling, $storage)
            );
        } finally {
            rmdir($prefixSibling);
            rmdir($storage);
        }
    }

    public function testIsPathWithinStorageRejectsCanonicalPathOutsideStorage()
    {
        $base = sys_get_temp_dir() . DS . 'personal-drive-' . bin2hex(random_bytes(4));
        $storage = $base . DS . 'storage';
        $outside = $base . DS . 'outside';
        mkdir($base);
        mkdir($storage);
        mkdir($outside);

        try {
            $this->assertFalse(
                $this->fileDeleteService->isPathWithinStorage(
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
        $filesDeleted = $this->fileDeleteService->deleteFiles($builder, sys_get_temp_dir());

        $this->assertSame(1, $filesDeleted);
        $this->assertDirectoryDoesNotExist($this->tempDir . $tempSubDir);
    }

    public function testDeleteFilesDoesNotDeleteFileOutsideStorage()
    {
        $outsideDir = sys_get_temp_dir() . DS . 'pd-outside-' . bin2hex(random_bytes(4));
        @mkdir($outsideDir);
        $outsideFile = 'outside.txt';
        file_put_contents($outsideDir . DS . $outsideFile, 'content');

        $file = LocalFile::factory()->create(
            [
            'private_path' => $outsideDir . DS,
            'filename' => $outsideFile,
            'is_dir' => 0,
            ]
        );

        $builder = LocalFile::whereIn('id', [$file->id]);
        $filesDeleted = $this->fileDeleteService->deleteFiles($builder, $this->tempDir);

        $this->assertSame(0, $filesDeleted);
        $this->assertFileExists($outsideDir . DS . $outsideFile);

        @unlink($outsideDir . DS . $outsideFile);
        @rmdir($outsideDir);
    }

    public function testDeleteFilesDoesNotDeleteSymlinkEscapingStorage()
    {
        $storageRoot = sys_get_temp_dir() . DS . 'pd-storage-' . bin2hex(random_bytes(4));
        @mkdir($storageRoot);

        $outsideDir = sys_get_temp_dir() . DS . 'pd-symlink-target-' . bin2hex(random_bytes(4));
        @mkdir($outsideDir);
        $targetFile = 'target.txt';
        file_put_contents($outsideDir . DS . $targetFile, 'content');

        $symlinkName = 'escape.txt';
        $symlinkPath = $storageRoot . DS . $symlinkName;
        @symlink($outsideDir . DS . $targetFile, $symlinkPath);

        $file = LocalFile::factory()->create(
            [
            'private_path' => $storageRoot . DS,
            'filename' => $symlinkName,
            'is_dir' => 0,
            ]
        );

        $builder = LocalFile::whereIn('id', [$file->id]);
        $filesDeleted = $this->fileDeleteService->deleteFiles($builder, $storageRoot);

        $this->assertSame(0, $filesDeleted);
        $this->assertFileExists($symlinkPath);
        $this->assertFileExists($outsideDir . DS . $targetFile);

        @unlink($symlinkPath);
        @rmdir($storageRoot);
        @unlink($outsideDir . DS . $targetFile);
        @rmdir($outsideDir);
    }

    public function testDeleteFilesDoesNotDeleteDirectoryOutsideStorage()
    {
        $outsideDir = sys_get_temp_dir() . DS . 'pd-outside-dir-' . bin2hex(random_bytes(4));
        @mkdir($outsideDir);

        $dir = LocalFile::factory()->create(
            [
            'private_path' => sys_get_temp_dir() . DS,
            'filename' => basename($outsideDir),
            'is_dir' => 1,
            ]
        );

        $builder = LocalFile::whereIn('id', [$dir->id]);
        $filesDeleted = $this->fileDeleteService->deleteFiles($builder, $this->tempDir);

        $this->assertSame(0, $filesDeleted);
        $this->assertDirectoryExists($outsideDir);

        @rmdir($outsideDir);
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
