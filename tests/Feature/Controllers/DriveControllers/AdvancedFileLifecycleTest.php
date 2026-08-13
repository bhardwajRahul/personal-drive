<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BaseFeatureTest;

class AdvancedFileLifecycleTest extends BaseFeatureTest
{
    public function test_overwrite_download_delete_and_reupload_same_path(): void
    {
        $filename = 'report.txt';
        $this->postUpload([UploadedFile::fake()->createWithContent($filename, 'version one')], 'work');
        $original = LocalFile::where('filename', $filename)->where('public_path', 'work')->firstOrFail();

        $duplicateResponse = $this->postUpload(
            [UploadedFile::fake()->createWithContent($filename, 'version two')],
            'work'
        );
        $duplicateResponse->assertSessionHas('message', 'Duplicates Detected');

        $this->post(route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'overwrite',
        ])->assertSessionHas('status', true);

        $download = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$original->id],
        ]);
        $download->assertOk();
        $this->assertSame('version two', $download->streamedContent());

        $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$original->id],
        ])->assertSessionHas('status', true);

        $this->assertDatabaseMissing('local_files', ['id' => $original->id]);
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/work/' . $filename);

        $this->postUpload([UploadedFile::fake()->createWithContent($filename, 'version three')], 'work');
        $replacement = LocalFile::where('filename', $filename)->where('public_path', 'work')->firstOrFail();

        $this->assertNotSame($original->id, $replacement->id);
        $this->assertSame('version three', $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$replacement->id],
        ])->streamedContent());
    }

    public function test_move_nested_directory_then_delete_moved_directory(): void
    {
        $this->uploadMultipleFiles('', [
            'workspace/src/main.php',
            'workspace/src/lib/util.php',
            'workspace/docs/readme.txt',
            'archive/.keep',
        ]);
        $workspace = LocalFile::where('filename', 'workspace')->where('public_path', '')->firstOrFail();

        $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => [$workspace->id],
            'path' => 'archive',
        ])->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/workspace/src/main.php');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/archive/workspace/src/main.php');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/archive/workspace/src/lib/util.php');

        $movedWorkspace = LocalFile::where('filename', 'workspace')->where('public_path', 'archive')->firstOrFail();
        $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$movedWorkspace->id],
        ])->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/archive/workspace');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/archive/.keep');
        $this->assertDatabaseMissing('local_files', ['filename' => 'main.php', 'public_path' => 'archive/workspace/src']);
        $this->assertDatabaseMissing('local_files', ['filename' => 'util.php', 'public_path' => 'archive/workspace/src/lib']);
        $this->assertDatabaseHas('local_files', ['filename' => '.keep', 'public_path' => 'archive']);
    }

    public function test_deleting_parent_directory_removes_all_descendants_and_keeps_siblings(): void
    {
        $this->uploadMultipleFiles('', [
            'drop/a.txt',
            'drop/deep/b.txt',
            'drop/deep/further/c.txt',
            'keep.txt',
        ]);
        $drop = LocalFile::where('filename', 'drop')->where('public_path', '')->firstOrFail();
        $keep = LocalFile::where('filename', 'keep.txt')->where('public_path', '')->firstOrFail();

        $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$drop->id],
        ])->assertSessionHas('message', 'Deleted 1 files');

        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/drop');
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/keep.txt');
        $this->assertDatabaseMissing('local_files', ['filename' => 'a.txt', 'public_path' => 'drop']);
        $this->assertDatabaseMissing('local_files', ['filename' => 'b.txt', 'public_path' => 'drop/deep']);
        $this->assertDatabaseMissing('local_files', ['filename' => 'c.txt', 'public_path' => 'drop/deep/further']);

        $download = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$keep->id],
        ]);
        $download->assertOk();
        $download->assertHeader('Content-Disposition', 'attachment; filename=keep.txt');
    }

    public function test_guest_cannot_download_private_file_after_share_password_flow(): void
    {
        $this->uploadMultipleFiles('', ['shared.txt', 'private.txt']);
        $shared = LocalFile::where('filename', 'shared.txt')->firstOrFail();
        $private = LocalFile::where('filename', 'private.txt')->firstOrFail();
        $slug = 'advanced-lifecycle';

        $this->createShare([$shared->id], 'password', 7, $slug);
        $this->logout();
        $this->postCheckPassword($slug, 'password')->assertRedirect('/shared/' . $slug);

        $response = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$private->id],
            'slug' => $slug,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => false,
            'message' => 'Error: authorization issue',
        ]);
        $this->assertStringNotContainsString('private.txt', $response->getContent());
    }

    public function test_unauthenticated_request_cannot_download_private_file_by_id(): void
    {
        $this->uploadFile('', 'private.txt');
        $private = LocalFile::where('filename', 'private.txt')->firstOrFail();
        $this->logout();

        $response = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$private->id],
        ]);

        $response->assertRedirect(route('rejected'));
        $this->assertStringNotContainsString('private.txt', $response->getContent());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
