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



    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
