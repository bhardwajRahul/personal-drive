<?php

namespace Tests\Feature\Api;

use App\Models\Share;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\BaseFeatureTest;

class InertiaShareTest extends BaseFeatureTest
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }

    // ── Shares List Page ─────────────────────────────────────────────

    public function test_shares_list_page_renders_correct_component(): void
    {
        $response = $this->get('shares-all');
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page->component('Drive/Shares/AllShares')
        );
    }

    public function test_shares_list_passes_shares(): void
    {
        list($toShareFileIds, $password, $expiry) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, $password, $expiry, 'share-one');
        $this->createShare($toShareFileIds, $password, $expiry, 'share-two');

        $shares = Share::all();
        $response = $this->get('shares-all');
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares', count($shares))
                ->where('totalShares', count($shares))
        );
    }

    public function test_shares_list_empty(): void
    {
        $response = $this->get('shares-all');
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Drive/Shares/AllShares')
                ->has('shares')
                ->count('shares', 0)
        );
    }

    // ── Shared Guest Page ────────────────────────────────────────────

    public function test_shared_page_renders_guest_home(): void
    {
        $slug = 'test-guest-share';
        // Create share without password for direct guest access
        list($toShareFileIds) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, '', -1, $slug);
        $this->logout();

        $response = $this->get('/shared/' . $slug);
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->where('slug', $slug)
        );
    }

    public function test_shared_page_passes_files(): void
    {
        $slug = 'files-share';
        // Create share without password for direct guest access
        list($toShareFileIds) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, '', -1, $slug);
        $this->logout();

        $response = $this->get('/shared/' . $slug);
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Drive/ShareFilesGuestHome')
                ->has('files')
        );
    }

    public function test_shared_password_page_renders_correct_component(): void
    {
        $slug = 'password-share';
        list($toShareFileIds, $password, $expiry) = $this->getDataForMakingShare();
        $this->createShare($toShareFileIds, $password, $expiry, $slug);
        $this->logout();

        $response = $this->get('/shared-password/' . $slug);
        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Drive/Shares/CheckSharePassword')
                ->where('slug', $slug)
        );
    }
}
