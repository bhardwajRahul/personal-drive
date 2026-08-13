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

    public function test_moving_directory_into_its_descendant_is_rejected_without_data_loss(): void
    {
        $this->uploadMultipleFiles('', ['root/child/keep.txt']);
        $root = LocalFile::where('filename', 'root')->where('public_path', '')->firstOrFail();

        $response = $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => [$root->id],
            'path' => 'root/child',
        ]);

        $response->assertSessionHas('status', false);
        Storage::disk('local')->assertExists(CONTENT_SUBDIR . '/root/child/keep.txt');
        Storage::disk('local')->assertMissing(CONTENT_SUBDIR . '/root/child/root/child/keep.txt');
        $this->assertDatabaseHas('local_files', ['filename' => 'keep.txt', 'public_path' => 'root/child']);
    }


    public function test_deleting_one_shared_file_keeps_other_shared_file_accessible(): void
    {
        $this->uploadMultipleFiles('', ['share/a.txt', 'share/b.txt']);
        $first = LocalFile::where('filename', 'a.txt')->firstOrFail();
        $second = LocalFile::where('filename', 'b.txt')->firstOrFail();
        $slug = 'partial-delete-share';
        $this->createShare([$first->id, $second->id], 'password', 7, $slug);

        $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$first->id],
        ])->assertSessionHas('status', true);
        $this->logout();
        $this->postCheckPassword($slug, 'password');

        $missing = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$first->id],
            'slug' => $slug,
        ]);
        $missing->assertOk();
        $missing->assertJson(['status' => false, 'message' => 'Could not find files to download']);

        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$second->id],
            'slug' => $slug,
        ])->assertHeader('Content-Disposition', 'attachment; filename=b.txt');
    }

    public function test_paused_share_revokes_an_authenticated_guest_session(): void
    {
        $this->uploadFile('', 'shared.txt');
        $shared = LocalFile::where('filename', 'shared.txt')->firstOrFail();
        $slug = 'paused-share';
        $this->createShare([$shared->id], 'password', 7, $slug);
        $shareId = $this->getSlugId($slug);
        $this->logout();
        $this->postCheckPassword($slug, 'password');

        $this->actingAs(\App\Models\User::firstOrFail());
        $this->post(route('drive.share-pause'), ['_token' => csrf_token(), 'id' => $shareId])
            ->assertSessionHas('status', true);
        $this->logout();

        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$shared->id],
            'slug' => $slug,
        ])->assertRedirect(route('login', ['slug' => $slug]));
    }

    public function test_deleting_share_revokes_an_authenticated_guest_session(): void
    {
        $this->uploadFile('', 'shared.txt');
        $shared = LocalFile::where('filename', 'shared.txt')->firstOrFail();
        $slug = 'deleted-share';
        $this->createShare([$shared->id], 'password', 7, $slug);
        $shareId = $this->getSlugId($slug);
        $this->logout();
        $this->postCheckPassword($slug, 'password');

        $this->actingAs(\App\Models\User::firstOrFail());
        $this->post(route('drive.share-delete'), ['_token' => csrf_token(), 'id' => $shareId])
            ->assertSessionHas('status', true);
        $this->assertDatabaseMissing('shares', ['id' => $shareId]);
        $this->logout();

        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$shared->id],
            'slug' => $slug,
        ])->assertRedirect(route('login', ['slug' => $slug]));
    }

    public function test_overlapping_shares_are_revoked_independently(): void
    {
        $this->uploadMultipleFiles('', ['a.txt', 'b.txt', 'c.txt']);
        $a = LocalFile::where('filename', 'a.txt')->firstOrFail();
        $b = LocalFile::where('filename', 'b.txt')->firstOrFail();
        $c = LocalFile::where('filename', 'c.txt')->firstOrFail();
        $this->createShare([$a->id, $b->id], 'password', 7, 'first-share');
        $this->createShare([$b->id, $c->id], 'password', 7, 'second-share');

        $this->post(route('drive.share-pause'), [
            '_token' => csrf_token(),
            'id' => $this->getSlugId('first-share'),
        ])->assertSessionHas('status', true);
        $this->logout();

        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$b->id],
            'slug' => 'first-share',
        ])->assertRedirect(route('login', ['slug' => 'first-share']));

        $this->postCheckPassword('second-share', 'password');
        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$b->id, $c->id],
            'slug' => 'second-share',
        ])->assertHeaderContains('Content-Disposition', 'attachment; filename=personal_drive_');
    }

    public function test_batch_upload_keeps_existing_conflict_until_overwrite_then_preserves_other_files(): void
    {
        $this->postUpload([
            UploadedFile::fake()->createWithContent('a.txt', 'old a'),
            UploadedFile::fake()->createWithContent('b.txt', 'old b'),
        ], '');
        $a = LocalFile::where('filename', 'a.txt')->firstOrFail();
        $b = LocalFile::where('filename', 'b.txt')->firstOrFail();

        $this->postUpload([
            UploadedFile::fake()->createWithContent('a.txt', 'new a'),
            UploadedFile::fake()->createWithContent('b.txt', 'new b'),
            UploadedFile::fake()->createWithContent('c.txt', 'new c'),
        ], '')->assertSessionHas('message', 'Duplicates Detected');
        $this->post(route('drive.abort-replace'), ['_token' => csrf_token(), 'action' => 'overwrite'])
            ->assertSessionHas('status', true);

        $this->assertSame('new a', $this->post('/download-files', [
            '_token' => csrf_token(), 'fileList' => [$a->id],
        ])->streamedContent());
        $this->assertSame('new b', $this->post('/download-files', [
            '_token' => csrf_token(), 'fileList' => [$b->id],
        ])->streamedContent());
        $this->assertSame('new c', $this->post('/download-files', [
            '_token' => csrf_token(), 'fileList' => [LocalFile::where('filename', 'c.txt')->firstOrFail()->id],
        ])->streamedContent());
    }


    public function test_deep_repeated_names_share_only_selected_ancestry(): void
    {
        $this->uploadMultipleFiles('', [
            'a/readme.txt',
            'a/b/readme.txt',
            'a/b/c/readme.txt',
            'a-other/readme.txt',
        ]);
        $directory = LocalFile::where('filename', 'a')->where('public_path', '')->firstOrFail();
        $allowed = LocalFile::where('filename', 'readme.txt')->where('public_path', 'a/b/c')->firstOrFail();
        $outside = LocalFile::where('filename', 'readme.txt')->where('public_path', 'a-other')->firstOrFail();
        $slug = 'deep-ancestry';
        $this->createShare([$directory->id], 'password', 7, $slug);
        $this->logout();
        $this->postCheckPassword($slug, 'password');

        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$allowed->id],
            'slug' => $slug,
        ])->assertHeader('Content-Disposition', 'attachment; filename=readme.txt');

        $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$outside->id],
            'slug' => $slug,
        ])->assertJson(['status' => false, 'message' => 'Error: authorization issue']);
    }

    public function test_deleted_shared_descendant_never_returns_stale_file_content(): void
    {
        $this->uploadMultipleFiles('', ['shared/file.txt']);
        $directory = LocalFile::where('filename', 'shared')->where('public_path', '')->firstOrFail();
        $file = LocalFile::where('filename', 'file.txt')->where('public_path', 'shared')->firstOrFail();
        $slug = 'deleted-descendant';
        $this->createShare([$directory->id], 'password', 7, $slug);

        $this->post(route('drive.delete-files'), [
            '_token' => csrf_token(),
            'fileList' => [$directory->id],
        ])->assertSessionHas('status', true);
        $this->logout();
        $this->postCheckPassword($slug, 'password');

        $response = $this->post('/download-files', [
            '_token' => csrf_token(),
            'fileList' => [$file->id],
            'slug' => $slug,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => false, 'message' => 'Could not find files to download']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
