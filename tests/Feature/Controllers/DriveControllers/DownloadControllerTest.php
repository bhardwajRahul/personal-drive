<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Illuminate\Http\UploadedFile;
use Tests\Feature\BaseFeatureTest;
use ZipArchive;

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

    protected function tearDown(): void
    {
        // ignore_user_abort is process-global in the test run; restore it so
        // the abort-safety assertion doesn't leak into sibling tests.
        ignore_user_abort(false);
        parent::tearDown();
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
        $overwriteResponse->assertSessionHas('message', 'New files copied: 0. Files overwritten: 1');

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
    public function test_index_returns_not_found_when_file_is_missing_from_storage(): void
    {
        $file = LocalFile::getByName('ace.txt')->firstOrFail();

        $this->assertFileExists($file->getPrivatePathNameForFile());
        $this->assertTrue(unlink($file->getPrivatePathNameForFile()));

        $response = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$file->id],
        ]);

        $response->assertNotFound();
        $response->assertJson(['status' => false]);
        $this->assertDatabaseHas('local_files', ['id' => $file->id]);
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

    public function test_zip_download_survives_abort_and_leaves_no_temp_zip_behind(): void
    {
        $fileIds = LocalFile::all()->slice(0, 2)->pluck('id')->toArray();
        $before = count(glob('/tmp/personal_drive_*.zip'));

        $response = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => $fileIds,
        ]);

        $response->assertOk();
        $response->streamedContent();
        $this->assertSame(1, ignore_user_abort());
        $this->assertSame($before, count(glob('/tmp/personal_drive_*.zip')));
    }

    public function test_index_zips_a_selected_directory_and_explicit_descendant_only_once(): void
    {
        $contents = 'selected descendant contents';

        $this->postUpload(
            [UploadedFile::fake()->createWithContent('selected-folder/descendant.txt', $contents)],
            ''
        );

        $directory = LocalFile::where('filename', 'selected-folder')
            ->where('is_dir', true)
            ->firstOrFail();
        $descendant = LocalFile::where('filename', 'descendant.txt')
            ->where('public_path', 'selected-folder')
            ->firstOrFail();

        $response = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$directory->id, $descendant->id],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('.zip', $response->headers->get('Content-Disposition'));

        $archivePath = tempnam(sys_get_temp_dir(), 'download-zip-');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $opened = $archive->open($archivePath);
        try {
            $this->assertTrue($opened === true);

            $entries = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = $archive->getNameIndex($index);
                $this->assertNotFalse($entryName);
                $entries[] = [
                    'name' => $entryName,
                    'contents' => $archive->getFromIndex($index),
                ];
            }

            $this->assertSame([
                [
                    'name' => 'selected-folder/descendant.txt',
                    'contents' => $contents,
                ],
            ], $entries);
        } finally {
            if ($opened === true) {
                $archive->close();
            }

            unlink($archivePath);
        }
    }


    public function test_index_downloads_all_distinct_contents_when_zipping_same_named_files_from_different_paths(): void
    {
        $alphaContents = 'alpha readme bytes';
        $betaContents = 'beta readme bytes';

        $this->postUpload(
            [
                UploadedFile::fake()->createWithContent('alpha/readme.txt', $alphaContents),
                UploadedFile::fake()->createWithContent('beta/readme.txt', $betaContents),
            ],
            ''
        );

        $fileIds = LocalFile::where('filename', 'readme.txt')
            ->whereIn('public_path', ['alpha', 'beta'])
            ->pluck('id')
            ->all();

        $this->assertCount(2, $fileIds);

        $response = $this->post(
            '/download-files',
            [
                '_token' => csrf_token(),
                'fileList' => $fileIds,
            ]
        );

        $response->assertOk();

        $archivePath = tempnam(sys_get_temp_dir(), 'download-zip-');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;

        $opened = $archive->open($archivePath);

        try {
            $this->assertTrue($opened === true);
            $this->assertSame(2, $archive->numFiles);

            $entries = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = $archive->getNameIndex($index);
                $this->assertNotFalse($entryName);
                $entries[$entryName] = $archive->getFromIndex($index);
            }
            ksort($entries);

            $this->assertSame([
                'alpha/readme.txt' => $alphaContents,
                'beta/readme.txt' => $betaContents,
            ], $entries);
        } finally {
            if ($opened === true) {
                $archive->close();
            }

            unlink($archivePath);
        }
    }
}
