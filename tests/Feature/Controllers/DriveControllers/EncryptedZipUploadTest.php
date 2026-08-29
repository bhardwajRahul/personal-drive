<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BaseFeatureTest;
use ZipArchive;

class EncryptedZipUploadTest extends BaseFeatureTest
{
    private string $testPassword = 's3cret!Pass';
    private string $wrongPassword = 'wrongpass';

    public function test_upload_encrypted_zip_success()
    {
        $zipPath = $this->createEncryptedZip('encrypted.zip', ['secret.txt' => 'top secret'], $this->testPassword);
        $file = new UploadedFile($zipPath, 'encrypted.zip', 'application/zip', null, true);

        $response = $this->post(
            route('drive.upload'),
            ['_token' => csrf_token(), 'files' => [$file], 'path' => '']
        );

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($v) => str_contains($v, 'Files uploaded: 1'));
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'encrypted.zip');
    }

    public function test_encrypted_zip_is_valid_zip()
    {
        $zipPath = $this->createEncryptedZip('verify.zip', ['data.txt' => 'payload'], $this->testPassword);
        $file = new UploadedFile($zipPath, 'verify.zip', 'application/zip', null, true);

        $this->post(route('drive.upload'), ['_token' => csrf_token(), 'files' => [$file], 'path' => '']);
        $this->generateStats();

        $storedPath = Storage::disk('local')->path(CONTENT_SUBDIR . DS . 'verify.zip');
        $this->assertFileExists($storedPath);

        $zip = new ZipArchive();
        $result = $zip->open($storedPath);
        $this->assertTrue($result === true || $result === ZipArchive::ER_ENCRPWD);
    }

    public function test_encrypted_zip_rejects_wrong_password()
    {
        $zipPath = $this->createEncryptedZip('wrongpw.zip', ['file.txt' => 'content'], $this->testPassword);
        $file = new UploadedFile($zipPath, 'wrongpw.zip', 'application/zip', null, true);

        $this->post(route('drive.upload'), ['_token' => csrf_token(), 'files' => [$file], 'path' => '']);
        $this->generateStats();

        $storedPath = Storage::disk('local')->path(CONTENT_SUBDIR . DS . 'wrongpw.zip');

        $zip = new ZipArchive();
        $zip->open($storedPath);
        $zip->setPassword($this->wrongPassword);
        $contents = $zip->getFromName('file.txt');
        $zip->close();

        // Wrong password returns false (can't decrypt)
        $this->assertFalse($contents);
    }

    public function test_encrypted_zip_opens_with_correct_password()
    {
        $expected = 'readable content';
        $zipPath = $this->createEncryptedZip('correctpw.zip', ['doc.txt' => $expected], $this->testPassword);
        $file = new UploadedFile($zipPath, 'correctpw.zip', 'application/zip', null, true);

        $this->post(route('drive.upload'), ['_token' => csrf_token(), 'files' => [$file], 'path' => '']);
        $this->generateStats();

        $storedPath = Storage::disk('local')->path(CONTENT_SUBDIR . DS . 'correctpw.zip');

        $zip = new ZipArchive();
        $zip->open($storedPath);
        $zip->setPassword($this->testPassword);
        $contents = $zip->getFromName('doc.txt');
        $zip->close();

        $this->assertEquals($expected, $contents);
    }

    public function test_encrypted_zip_download_is_valid()
    {
        $zipPath = $this->createEncryptedZip('download.zip', ['report.txt' => 'data'], $this->testPassword);
        $file = new UploadedFile($zipPath, 'download.zip', 'application/zip', null, true);

        $this->post(route('drive.upload'), ['_token' => csrf_token(), 'files' => [$file], 'path' => '']);
        $this->generateStats();

        $localFile = LocalFile::getByName('download.zip')->firstOrFail();
        $this->assertNotNull($localFile);

        $response = $this->post('/download-files', ['fileList' => [$localFile->id]]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=download.zip');

        // Verify the stored file is still a valid encrypted zip
        $storedPath = Storage::disk('local')->path(CONTENT_SUBDIR . DS . 'download.zip');
        $zip = new ZipArchive();
        $zip->open($storedPath);
        $zip->setPassword($this->testPassword);
        $this->assertEquals('data', $zip->getFromName('report.txt'));
        $zip->close();
    }

    public function test_encrypted_zip_multiple_files()
    {
        $files = ['a.txt' => 'alpha', 'b.txt' => 'beta', 'c.txt' => 'gamma'];
        $zipPath = $this->createEncryptedZip('multi.zip', $files, $this->testPassword);
        $file = new UploadedFile($zipPath, 'multi.zip', 'application/zip', null, true);

        $this->post(route('drive.upload'), ['_token' => csrf_token(), 'files' => [$file], 'path' => '']);
        $this->generateStats();

        $storedPath = Storage::disk('local')->path(CONTENT_SUBDIR . DS . 'multi.zip');

        $zip = new ZipArchive();
        $zip->open($storedPath);
        $zip->setPassword($this->testPassword);

        $this->assertEquals('alpha', $zip->getFromName('a.txt'));
        $this->assertEquals('beta', $zip->getFromName('b.txt'));
        $this->assertEquals('gamma', $zip->getFromName('c.txt'));
        $this->assertEquals(3, $zip->numFiles);
        $zip->close();
    }

    public function test_encrypted_zip_with_folders()
    {
        $entries = [
            'readme.txt' => 'hello',
            'docs/notes.txt' => 'notes content',
            'src/main.txt' => 'main content',
        ];
        $zipPath = $this->createEncryptedZip('folders.zip', $entries, $this->testPassword);
        $file = new UploadedFile($zipPath, 'folders.zip', 'application/zip', null, true);

        $this->post(route('drive.upload'), ['_token' => csrf_token(), 'files' => [$file], 'path' => '']);
        $this->generateStats();

        $storedPath = Storage::disk('local')->path(CONTENT_SUBDIR . DS . 'folders.zip');

        $zip = new ZipArchive();
        $zip->open($storedPath);
        $zip->setPassword($this->testPassword);

        $this->assertEquals('hello', $zip->getFromName('readme.txt'));
        $this->assertEquals('notes content', $zip->getFromName('docs/notes.txt'));
        $this->assertEquals('main content', $zip->getFromName('src/main.txt'));
        $zip->close();
    }

    public function test_encrypted_zip_upload_with_path()
    {
        $zipPath = $this->createEncryptedZip('subdir.zip', ['data.txt' => 'stored in sub'], $this->testPassword);
        $file = new UploadedFile($zipPath, 'subdir.zip', 'application/zip', null, true);

        $response = $this->post(
            route('drive.upload'),
            ['_token' => csrf_token(), 'files' => [$file], 'path' => 'my/folder']
        );

        $response->assertSessionHas('status', true);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . DS . 'my/folder/subdir.zip');
    }

    private function createEncryptedZip(string $name, array $entries, string $password): string
    {
        $tmpDir = sys_get_temp_dir() . '/enc_zip_' . bin2hex(random_bytes(4));
        mkdir($tmpDir, 0755, true);
        $zipPath = $tmpDir . '/' . $name;

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->setPassword($password);

        foreach ($entries as $entryName => $content) {
            $zip->addFromString($entryName, $content);
            $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
        }

        $zip->close();
        return $zipPath;
    }

    private function generateStats(): void
    {
        $statsService = app(\App\Services\LocalFileStatsService::class);
        $pathService = app(\App\Services\PathService::class);
        $privatePath = $pathService->genPrivatePathFromPublic('');
        $statsService->generateStats('');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
