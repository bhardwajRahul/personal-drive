<?php

namespace Tests\Feature\Api;

use App\Models\Favorite;
use App\Models\LocalFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\BaseFeatureTest;

class InertiaDriveTest extends BaseFeatureTest
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }

    public function test_drive_page_renders_correct_inertia_component(): void
    {
        $response = $this->get(route('drive'));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->component('Drive/DriveHome')
        );
    }

    public function test_drive_page_passes_files(): void
    {
        $this->uploadMultipleFiles('');

        $response = $this->get(route('drive'));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->has('files')
        );
    }

    public function test_drive_page_passes_path(): void
    {
        $this->uploadMultipleFiles('foo');

        $response = $this->get(route('drive', ['path' => 'foo']));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->where('path', '/drive/foo')
        );
    }

    public function test_drive_page_passes_favorites(): void
    {
        $this->uploadMultipleFiles('');
        $user = \Auth::user();
        $file = LocalFile::first();

        Favorite::create([
            'user_id' => $user->id,
            'local_file_id' => $file->id,
        ]);

        $response = $this->get(route('drive'));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->has('favorites')
        );
    }

    public function test_drive_page_passes_folder_exists_true(): void
    {
        $this->uploadMultipleFiles('myfolder');

        $response = $this->get(route('drive', ['path' => 'myfolder']));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->where('folderExists', true)
        );
    }

    public function test_drive_page_passes_folder_exists_false(): void
    {
        $response = $this->get(route('drive', ['path' => 'nonexistent']));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->where('folderExists', false)
        );
    }

    public function test_drive_page_empty_root(): void
    {
        $response = $this->get(route('drive'));
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->count('files', 0)
        );
    }

    public function test_search_page_renders_drive_home_with_search_flag(): void
    {
        $this->uploadMultipleFiles('');
        $response = $this->post(route('drive.search'), [
            '_token' => csrf_token(),
            'query' => 'ace',
        ]);
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Drive/DriveHome')
                ->where('searchResults', true)
                ->has('files')
        );
    }

    public function test_search_results_contain_matching_files(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $this->postUpload([$file], '');

        $response = $this->post(route('drive.search'), [
            '_token' => csrf_token(),
            'query' => 'test',
        ]);
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->has('files')
                ->where('searchResults', true)
        );
        // Verify the matching file is in the results via viewData
        $page = json_decode(json_encode($response->viewData('page')), true);
        $filenames = collect($page['props']['files'])->pluck('filename')->toArray();
        $this->assertContains('test.txt', $filenames);
    }
}
