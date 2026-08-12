<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Illuminate\Http\UploadedFile;
use Tests\Feature\BaseFeatureTest;

class DownloadControllerTest extends BaseFeatureTest
{
    public $fileNames = [
        'ace.txt', 'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }

    public function test_index_downloads_single_file_successfully(): void
    {
        $firstFile = LocalFile::getByName('ace.txt')->firstOrFail();
        $privatePath = $firstFile->getPrivatePathNameForFile();

        $response = $this->post(
            '/download-files', [
                '_token' => csrf_token(),
                'fileList' => [$firstFile->id],
            ]
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=ace.txt');
        $response->streamedContent();

        $this->assertFileExists($privatePath);
    }

    public function test_download_returns_overwritten_file_contents(): void
    {
        $fileName = 'replace-me.txt';
        $originalContents = 'original contents';
        $replacementContents = 'replacement contents';

        $this->postUpload(
            [UploadedFile::fake()->createWithContent($fileName, $originalContents)],
            ''
        );
        $file = LocalFile::where('filename', $fileName)->firstOrFail();

        $duplicateResponse = $this->postUpload(
            [UploadedFile::fake()->createWithContent($fileName, $replacementContents)],
            ''
        );
        $duplicateResponse->assertSessionHas('message', 'Duplicates Detected');

        $overwriteResponse = $this->post(
            route('drive.abort-replace'), [
                '_token' => csrf_token(),
                'action' => 'overwrite',
            ]
        );
        $overwriteResponse->assertSessionHas('status', true);
        $overwriteResponse->assertSessionHas('message', 'Overwritten successfully');

        $this->assertSame(
            $file->id,
            LocalFile::where('filename', $fileName)->firstOrFail()->id
        );

        $response = $this->post(
            '/download-files', [
                '_token' => csrf_token(),
                'fileList' => [$file->id],
            ]
        );

        $response->assertOk();
        $downloadedContents = $response->streamedContent();
        $this->assertSame($replacementContents, $downloadedContents);
        $this->assertNotSame($originalContents, $downloadedContents);
    }

    public function test_index_fails_with_non_existent_id(): void
    {
        $firstFile = LocalFile::getByName('ace.txt')->firstOrFail();

        $response = $this->post(
            '/download-files', [
                '_token' => csrf_token(),
                'fileList' => ['01kd2195rfbxe1pbavxwefk9wt'],
            ]
        );
        $response->assertJson(
            [
                'status' => false,
                'message' => 'Could not find files to download',
            ]
        );
    }

    public function test_index_downloads_multiple_files_as_zip(): void
    {
        $fileIds = LocalFile::all()->slice(0, 2)->pluck('id')->toArray();

        $response = $this->post(
            '/download-files', [
                '_token' => csrf_token(),
                'fileList' => $fileIds,
            ]
        );

        $response->assertStatus(200);
        $this->assertStringContainsString('.zip', $response->headers->get('Content-Disposition'));
    }
}
