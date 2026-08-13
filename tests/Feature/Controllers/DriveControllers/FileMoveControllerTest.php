<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\PathService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\BaseFeatureTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;

class FileMoveControllerTest extends BaseFeatureTest
{
    public string $targetDir;
    public mixed $pathService;
    protected mixed $uploadService;

    public function test_move_file_non_existent()
    {
        $testPath = 'bar';
        $response = $this->post(
            route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => [(string) Str::ulid()],
            'path' => $testPath
            ]
        );
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Could not find any valid files to move');
    }


    public function test_move_folders_exists_fail()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', 'bar')->where('public_path', '')->first();

        $response = $this->postMoveFiles([$firstFile->id], 'foo');
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Could not move: Same name Directory exists');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . $testPath . 'foo/bar/1.txt');
    }

    public function test_move_file_success()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', '1.txt')->where('public_path', 'bar')->first();

        $response = $this->postMoveFiles([$firstFile->id], 'foo');
        $response->assertSessionHas('status', true);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/foo/1.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . $testPath . '/bar/1.txt');
    }

    public function test_move_directory_success()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', 'foo')->where('is_dir', '1')->first();

        $response = $this->postMoveFiles([$firstFile->id], 'bar');
        $response->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . $testPath . '/foo/1.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/foo/ace.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/foo/bar/1.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/foo/bar/2.txt');
    }

    public function test_move_multiple_success()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', 'ace.txt')->first();
        $secondFile = LocalFile::where('filename', 'bar')->where('public_path', 'foo')->first();

        $response = $this->postMoveFiles([$firstFile->id, $secondFile->id], 'bar');
        $response->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . $testPath . '/foo/ace.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . $testPath . '/foo/bar/1.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . $testPath . '/foo/bar/2.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/ace.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/bar/1.txt');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . $testPath . '/bar/bar/2.txt');
    }

    public function test_moving_parent_and_child_together_creates_one_coherent_destination_tree()
    {
        $this->postUpload([
            \Illuminate\Http\UploadedFile::fake()->createWithContent('parent/child/file.txt', 'nested content'),
            \Illuminate\Http\UploadedFile::fake()->createWithContent('destination/.keep', 'keep'),
        ], '');
        $parent = LocalFile::where('filename', 'parent')->where('public_path', '')->firstOrFail();
        $child = LocalFile::where('filename', 'child')->where('public_path', 'parent')->firstOrFail();

        $response = $this->postMoveFiles([$parent->id, $child->id], 'destination');

        $response->assertSessionHas('status', true);
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/parent');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/destination/parent/child/file.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/destination/child/file.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/destination/parent/child/child/file.txt');
        $this->assertDatabaseMissing('local_files', ['filename' => 'parent', 'public_path' => '']);
        $this->assertSame(0, LocalFile::whereIn('public_path', ['parent', 'parent/child'])->count());
        $this->assertSame(1, LocalFile::where('filename', 'parent')->where('public_path', 'destination')->count());
        $this->assertSame(1, LocalFile::where('filename', 'child')->where('public_path', 'destination/parent')->count());
        $this->assertSame(1, LocalFile::where('filename', 'file.txt')
            ->where('public_path', 'destination/parent/child')->count());
        $this->assertSame(0, LocalFile::where('filename', 'file.txt')
            ->whereIn('public_path', ['destination/child', 'destination/parent/child/child'])->count());
    }

    public function test_moving_file_to_its_current_directory_preserves_one_downloadable_file()
    {
        $this->postUpload([
            \Illuminate\Http\UploadedFile::fake()->createWithContent('home/file.txt', 'original content'),
        ], '');
        $file = LocalFile::where('filename', 'file.txt')->where('public_path', 'home')->firstOrFail();

        $response = $this->postMoveFiles([$file->id], 'home');

        $response->assertSessionHas('status', fn (mixed $status): bool => $status === true || $status === false);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/home/file.txt');
        $this->assertSame(1, LocalFile::where('filename', 'file.txt')->where('public_path', 'home')->count());
        $preservedFile = LocalFile::where('filename', 'file.txt')->where('public_path', 'home')->firstOrFail();
        $download = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$preservedFile->id],
        ]);

        $download->assertOk();
        $this->assertSame('original content', $download->streamedContent());
    }

    public function postMoveFiles(array $fileIds, string $path): TestResponse
    {
        return $this->post(
            route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => $fileIds,
            'path' => $path
            ]
        );
    }

    public function setupUploadBeforeMove(): string
    {
        $testPath = '';
        $fileNames = [
            'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt', 'foo/bar/2.txt',
        ];

        $this->uploadMultipleFiles($testPath, $fileNames);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR.$testPath.'/bar/1.txt');
        return $testPath;
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
