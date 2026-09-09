<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Tests\Feature\BaseFeatureTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;

class FileDeleteControllerTest extends BaseFeatureTest
{
    public function test_delete_file_fake_ids()
    {
        $this->uploadFile('', 'dummy.txt', 100);
        $response = $this->post(
            route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [(string) Str::ulid()],
            ]
        );
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'No valid files in database. Try a ReSync first');
    }

    public function test_delete_file_success()
    {
        $name = 'dummy.txt';
        $this->uploadFile('', $name, 100);
        $firstFile = LocalFile::first();

        $response = $this->post(
            route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [(string) $firstFile->id],
            ]
        );
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Deleted 1 files');
    }

    public function test_delete_multiple_files_success()
    {
        $this->uploadMultipleFiles();
        $firstFile = LocalFile::where('filename', 'foo')->first();

        $response = $this->post(
            route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [(string) $firstFile->id],
            ]
        );
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Deleted 1 files');
        $remainingFiles = LocalFile::all();
        $this->assertCount(4, $remainingFiles);
    }
    public function test_delete_missing_file_removes_its_index_record(): void
    {
        $this->uploadFile('', 'missing.txt', 100);
        $file = LocalFile::firstOrFail();

        $this->assertFileExists($file->getPrivatePathNameForFile());
        $this->assertTrue(unlink($file->getPrivatePathNameForFile()));

        $response = $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$file->id],
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Deleted 1 files');
        $this->assertDatabaseMissing('local_files', ['id' => $file->id]);
    }
    public function test_delete_already_deleted(): void
    {
        // no chmod at all - the file is just gone from disk before we try
        $this->uploadFile('', 'o/i/f.txt', 100);
        $inner = LocalFile::where('filename', 'i')->first();

        // simulate external deletion, bypassing the app
        Storage::disk('local')->deleteDirectory('o/i');

        $response = $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$inner->id],
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Deleted 1 files');

        $this->assertDatabaseMissing('local_files', ['id' => $inner->id]);
    }

    public function test_delete_partial_success(): void
    {
        $this->uploadFile('', 'o/i/f.txt', 100);       // will be blocked
        $this->uploadFile('', 'o2/f2.txt', 100);       // will succeed

        $outer = LocalFile::where('filename', 'o')->first();
        $inner = LocalFile::where('filename', 'i')->first();
        $file2 = LocalFile::where('filename', 'f2.txt')->first();

        chmod($outer->getPrivatePathNameForFile(), 0555);

        $response = $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$inner->id, $file2->id],
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Deleted 1 files. 1 could not be deleted (read-only)');

        $this->assertDatabaseHas('local_files', ['id' => $inner->id]);
        $this->assertDatabaseMissing('local_files', ['id' => $file2->id]);

        chmod($outer->getPrivatePathNameForFile(), 0755);
    }

    public function test_delete_readonly(): void
    {
        $this->uploadFile('', 'o/i/f.txt', 100);
        $outer = LocalFile::where('filename', 'o')->first();
        $inner = LocalFile::where('filename', 'i')->first();
        chmod($outer->getPrivatePathNameForFile(), 0555);

        $response = $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$inner->id],
        ]);

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', '1 could not be deleted (read-only)');

        $this->assertDatabaseHas('local_files', ['id' => $inner->id]);
        $this->assertDirectoryExists($inner->getPrivatePathNameForFile());

        chmod($outer->getPrivatePathNameForFile(), 0755);
    }

    public function test_delete_unreadable(): void
    {
        $this->uploadFile('', 'o/i/f.txt', 100);
        $outer = LocalFile::where('filename', 'o')->first();
        $inner = LocalFile::where('filename', 'i')->first();
        chmod($outer->getPrivatePathNameForFile(), 0444);

        $response = $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$inner->id],
        ]);

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', '1 could not be accessed (permission denied)');

        $this->assertDatabaseHas('local_files', ['id' => $inner->id]);

        chmod($outer->getPrivatePathNameForFile(), 0755);
        $this->assertDirectoryExists($inner->getPrivatePathNameForFile());
    }

    protected function tearDown(): void
    {
        $path = Storage::disk('local')->path('storage_personaldrive/readonly');
        $path1 = Storage::disk('local')->path('storage_personaldrive/o');
        if (file_exists($path)) {
            chmod($path, 0755); // restore before Laravel/PHPUnit tries to clean up
        }
        if (file_exists($path1)) {
            chmod($path1, 0755); // restore before Laravel/PHPUnit tries to clean up
            @rmdir($path1);

        }
        parent::tearDown();
    }



    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
