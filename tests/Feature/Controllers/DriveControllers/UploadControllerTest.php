<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\FileOperationsService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;

use const true;

class UploadControllerTest extends BaseFeatureTest
{
    protected mixed $uploadService;
    private string $fileName = 'dummy.txt';
    private $tempRootDir;
    private $escapeSymlink;
    private $escapeOutsideDir;

    public function test_store_returns_error_when_no_files_uploaded()
    {
        $this->assertAuthenticated();
        $response = $this->post(
            route('drive.upload'), [
            '_token' => csrf_token(),
            'path' => '/some/path',
            ]
        );
        $response->assertSessionHasErrors(['files' => 'The files field is required.']);
    }

    public function test_create_upload_different_path_file_success()
    {
        $fileName = 'file.txt';
        $fileName2 = 'file2.txt';
        $fileName3 = 'file3.txt';

        $testPath = '';
        $testPath2 = 'foo/bar';
        $testPath3 = 'foo/bar/foo';

        $this->uploadFile($testPath, $fileName, 100);
        $this->uploadFile($testPath2, $fileName2, 100);
        $this->uploadFile($testPath3, $fileName3, 100);
    }

    public function test_create_upload_mulitple_files_success()
    {
        $testFileName2 = 'bar2/dummy2.txt';
        $file = UploadedFile::fake()->create($this->fileName, 100);
        $file2 = UploadedFile::fake()->create($testFileName2, 10);

        $testPath = 'foo/bar';
        $response = $this->postUpload([$file, $file2], $testPath);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Files uploaded: 2'));
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . $testPath . DS . $this->fileName);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . $testPath . DS . $testFileName2);
    }


    public function createItem(string $fileName, string $testPath = '', bool $isFile = true): TestResponse
    {
        return $this->post(
            route('drive.create-item'), [
            '_token' => csrf_token(),
            'itemName' => $fileName,
            'path' => $testPath,
            'isFile' => $isFile,
            ]
        );
    }

    public function test_create_folder_fail()
    {
        $fileOptsMock = $this->mockFileOperations();

        $fileOptsMock->shouldReceive('makeFolder')->withAnyArgs()->andReturn(false);
        $response = $this->createItem($this->fileName, '', false);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Create folder failed');
    }
    public function test_create_file_fail()
    {
        $fileOptsMock = $this->mockFileOperations();

        $fileOptsMock->shouldReceive('makeFile')->withAnyArgs()->andReturn(false);
        $response = $this->createItem($this->fileName, '', true);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Create file failed');


    }
    public function test_create_file_in_folder()
    {
        $this->fileName = 'foo';
        $response = $this->createItem($this->fileName, '', false);
        $this->successAsserts($response, '', 'folder');
        $this->fileName = 'dummy.txt';
        $response = $this->createItem($this->fileName, 'foo');
        $this->successAsserts($response, 'foo');
        $response = $this->createItem($this->fileName, 'foo');

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'File already exists');
    }

    public function successAsserts($response, $testPath = '', $fileFolder = 'file'): void
    {
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created ' . $fileFolder . ' successfully');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . ($testPath ? $testPath . '/' : '') . $this->fileName);
    }

    public function test_create_file_successfully()
    {
        $response = $this->createItem($this->fileName);
        $this->successAsserts($response, '', 'file');
        $response = $this->createItem($this->fileName);

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'File already exists');
    }

    public function test_create_folder_successfully()
    {
        $testPath = '';
        $testFolder = 'TestFolder';
        $response = $this->post(
            route('drive.create-item'), [
            '_token' => csrf_token(),
            'itemName' => $testFolder,
            'path' => $testPath,
            'isFile' => false,
            ]
        );

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created folder successfully');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . $testPath . $testFolder);
    }

    public function test_create_upload_folder_file_conflict_fail()
    {
        $testPath = 'some/path';
        $fileName1 = 'foo/bar';
        $fileName2 = 'foo/bar1';
        $fileName3 = 'foo/bar/more/path/file1';
        $this->uploadMultipleFiles($testPath, [$fileName1, $fileName2]);
        $files[] = UploadedFile::fake()->create($fileName3, 100);

        $response = $this->post(
            route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $files,
            'path' => $testPath
            ]
        );

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Conflicts'));
    }

    public function test_create_upload_file_folder_conflict_fail()
    {
        $testPath = 'some/path';
        $fileName1 = 'foo/bar/file1';
        $fileName2 = 'foo/bar/file2';
        $fileName3 = 'foo/bar';
        $this->uploadMultipleFiles($testPath, [$fileName1, $fileName2]);
        $files[] = UploadedFile::fake()->create($fileName3, 100);

        $response = $this->post(
            route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $files,
            'path' => $testPath
            ]
        );

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Conflicts'));
    }

    public function test_create_upload_folder_conflict_partial_success()
    {
        $testPath = 'some/path';
        $fileName1 = 'foo/bar/file1';
        $fileName2 = 'foo/bar/file2';
        $fileName3 = 'foo/bar';
        $fileName4 = 'foo/bar/file3';
        $this->uploadMultipleFiles($testPath, [$fileName1, $fileName2]);
        $response = $this->uploadMultipleFiles($testPath, [$fileName3, $fileName4]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Conflicts'));
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Files uploaded: 1 out of 2'));
    }

    public function test_create_upload_folder_duplicates_partial()
    {
        $this->uploadDuplicates();

        $response = $this->post(
            route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'overwrite'
            ]
        );

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'New files copied: 2. Files overwritten: 2');
        $response->assertSessionMissing('new_file_copied_num');
        $response->assertSessionMissing('duplicate_files_num');
    }

    public function test_overwrite_merges_nested_folder_upload_without_deleting_destination_only_files(): void
    {
        $this->postUpload([
            UploadedFile::fake()->createWithContent('workspace/replaced.txt', 'old content'),
            UploadedFile::fake()->createWithContent('workspace/old-only.txt', 'keep me'),
            UploadedFile::fake()->createWithContent('workspace/archive/deep.txt', 'keep nested'),
        ], '');
        $original = LocalFile::where('filename', 'replaced.txt')
            ->where('public_path', 'workspace')
            ->firstOrFail();

        $this->postUpload([
            UploadedFile::fake()->createWithContent('workspace/replaced.txt', 'new content'),
            UploadedFile::fake()->createWithContent('workspace/new-only.txt', 'add me'),
            UploadedFile::fake()->createWithContent('workspace/new/deep.txt', 'add nested'),
        ], '')->assertSessionHas('message', 'Duplicates Detected');

        $disk = Storage::disk('local');
        $this->assertSame('old content', $disk->get(CONTENT_SUBDIR . '/workspace/replaced.txt'));
        $this->assertSame('add me', $disk->get(CONTENT_SUBDIR . '/workspace/new-only.txt'));
        $this->post(route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'overwrite',
        ])->assertSessionHas('message', 'New files copied: 2. Files overwritten: 1');

        $this->assertSame('new content', $disk->get(CONTENT_SUBDIR . '/workspace/replaced.txt'));
        $this->assertSame('keep me', $disk->get(CONTENT_SUBDIR . '/workspace/old-only.txt'));
        $this->assertSame('keep nested', $disk->get(CONTENT_SUBDIR . '/workspace/archive/deep.txt'));
        $this->assertSame('add me', $disk->get(CONTENT_SUBDIR . '/workspace/new-only.txt'));
        $this->assertSame('add nested', $disk->get(CONTENT_SUBDIR . '/workspace/new/deep.txt'));
        $this->assertDatabaseHas('local_files', [
            'id' => $original->id,
            'filename' => 'replaced.txt',
            'public_path' => 'workspace',
        ]);
        $this->assertSame(
            1,
            LocalFile::where('filename', 'replaced.txt')->where('public_path', 'workspace')->count()
        );
    }

    public function uploadDuplicates()
    {
        $testPath = 'some/path';
        $files = ['foo/bar/file1', 'foo/bar/file2', 'foo/file3'];
        $this->uploadMultipleFiles($testPath, ['foo/bar/file1', 'foo/bar/file2', 'foo/file3']);
        $files1 = ['foo/bar/file3', 'foo/bar/file2', 'foo/file1', 'foo/file3'];
        $response = $this->uploadMultipleFiles($testPath, $files1);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Duplicates Detected'));
        $this->tempRootDir = $this->uploadService->getTempStorageDir();

        $this->assertTrue(
            collect(array_merge($files, $files1))->every(
                fn($file) => Storage::disk('local')->exists(
                    CONTENT_SUBDIR . DS . $testPath . DS . $file
                )
            )
        );
        $this->assertTrue(
            collect(array_intersect($files1, $files))->every(
                fn($file) => Storage::disk('local')->exists(
                    $this->tempRootDir . DS . $testPath . DS . $file
                )
            )
        );
        return $response;
    }

    public function test_create_upload_folder_duplicates_abort()
    {
        $this->uploadDuplicates();

        $response = $this->post(
            route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'abort'
            ]
        );

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'New files copied: 2. Files skipped: 2');
        $response->assertSessionMissing('new_file_copied_num');
        $response->assertSessionMissing('duplicate_files_num');
        $this->assertDirectoryDoesNotExist($this->tempRootDir);
    }

    public function test_create_upload_folder_duplicates_only_abort()
    {
        $testPath = 'some/path';
        $files = ['foo/file1'];
        $this->uploadMultipleFiles($testPath, $files);
        $this->uploadMultipleFiles($testPath, $files)
            ->assertSessionHas('message', 'Duplicates Detected');

        $response = $this->post(
            route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'abort'
            ]
        );

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'New files copied: 0. Files skipped: 1');
        $response->assertSessionMissing('new_file_copied_num');
        $response->assertSessionMissing('duplicate_files_num');
    }

    public function mockFileOperations()
    {
        $fileOptsMock = Mockery::mock(FileOperationsService::class)->makePartial();
        $this->app->instance(FileOperationsService::class, $fileOptsMock);
        return $fileOptsMock;
    }

    public function test_overwrite_does_not_follow_symlink_outside_storage(): void
    {
        $this->uploadDuplicates();
        Storage::disk('local')->deleteDirectory(CONTENT_SUBDIR . '/some/path/foo');
        $outsideDir = $this->makeSymlinkEscapingStorage('some/path/foo');

        $response = $this->post(
            route('drive.abort-replace'),
            [
                '_token' => csrf_token(),
                'action' => 'overwrite',
            ]
        );

        $response->assertSessionHas('status', false);
        $this->assertFileDoesNotExist($outsideDir . '/bar/file2');
    }

    public function test_upload_through_symlink_escaping_storage_fails_gracefully()
    {
        $outsideDir = $this->makeSymlinkEscapingStorage();

        $response = $this->post(
            route('drive.upload'),
            [
                '_token' => csrf_token(),
                'files' => [UploadedFile::fake()->create('escape/evil.txt', 10)],
                'path' => '',
            ]
        );

        $response->assertSessionHas('status', false);
        $response->assertSessionHas(
            'message',
            fn($value) => str_contains($value, 'outside the storage root')
        );
        $this->assertFileDoesNotExist($outsideDir . '/evil.txt');
    }

    public function test_create_item_under_symlink_escaping_storage_fails_gracefully()
    {
        $outsideDir = $this->makeSymlinkEscapingStorage();

        $response = $this->post(
            route('drive.create-item'),
            [
                '_token' => csrf_token(),
                'path' => 'escape',
                'itemName' => 'newfolder',
                'isFile' => false,
            ]
        );

        $response->assertSessionHas('status', false);
        $response->assertSessionHas(
            'message',
            fn($value) => str_contains($value, 'outside the storage root')
        );
        $this->assertDirectoryDoesNotExist($outsideDir . '/newfolder');
    }

    /**
     * Place a symlink inside the storage content root pointing outside it
     * (host-side bind-mount style attack), returns the outside target dir.
     */
    private function makeSymlinkEscapingStorage(string $relativePath = 'escape'): string
    {
        $outsideDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/escape_' . uniqid();
        mkdir($outsideDir, 0755, true);

        $contentRoot = Storage::disk('local')->path(CONTENT_SUBDIR);
        $link = $contentRoot . '/' . $relativePath;
        File::ensureDirectoryExists(dirname($link));
        if (!@symlink($outsideDir, $link)) {
            rmdir($outsideDir);
            $this->markTestSkipped('symlink() is not permitted on this host.');
        }
        $this->escapeSymlink = $link;
        $this->escapeOutsideDir = $outsideDir;

        return $outsideDir;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadService = app(UploadService::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->escapeSymlink) && is_link($this->escapeSymlink)) {
            unlink($this->escapeSymlink);
        }
        if (isset($this->escapeOutsideDir) && is_dir($this->escapeOutsideDir)) {
            rmdir($this->escapeOutsideDir);
        }
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
