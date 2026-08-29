<?php

namespace Tests\Feature\Controllers\AdminControllers;

use App\Models\LocalFile;
use App\Models\Setting;
use App\Services\FileOperationsService;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use Illuminate\Testing\TestResponse;
use Mockery;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\Feature\BaseFeatureTest;
use ReflectionProperty;
use UnexpectedValueException;

use const false;

class AdminConfigControllerTest extends BaseFeatureTest
{
    private string $newStoragePath = '';
    private $fileOptsMock;
    private $settingMock;

    public function test_index_returns_correct_view_with_data()
    {
        $response = $this->get(route('admin-config'));
        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Admin/Settings')
                ->hasAll(
                    [
                    'storage_path',
                    'php_max_upload_size',
                    'php_post_max_size',
                    'php_max_file_uploads',
                    'setupMode',
                    'tokens',
                    'flash',
                    ]
                )
        );
    }

    public function test_update_setting_success()
    {
        $this->postNewStoragePath();
    }

    public function test_update_setting_same_success()
    {
        $this->postNewStoragePath();
        $this->postNewStoragePath();
    }

    protected function assertSessionHas($response, string $message): void
    {
        $response->assertSessionHas(
            'message',
            fn($value) => str_contains($value, $message)
        );
    }

    public function test_update_setting_fail()
    {
        $this->settingMock->shouldReceive('updateStoragePath')->withAnyArgs()->andReturn(false);
        $response = $this->updateStoragePost(false);
        $this->assertSessionHas($response, 'Failed to save storage path setting');
    }

    public function test_update_shows_error_when_storage_scan_cannot_access_directory(): void
    {
        $this->uploadFile('', 'preserved.txt');
        $file = LocalFile::firstOrFail();

        $statsService = Mockery::mock(LocalFileStatsService::class);
        $statsService->shouldReceive('generateStats')
            ->once()
            ->andThrow(new UnexpectedValueException('Permission denied'));

        $controller = app('router')
            ->getRoutes()
            ->getByName('admin-config.update')
            ->getController();
        $statsServiceProperty = new ReflectionProperty($controller, 'localFileStatsService');
        $statsServiceProperty->setValue($controller, $statsService);

        $response = $this->setStoragePath($this->newStoragePath);
        $response->assertSessionHas('status', false);
        $response->assertRedirect(route('admin-config', ['setupMode' => true]));

        $this->assertSessionHas(
            $response,
            'Storage scan failed because a file or folder cannot be accessed. Check its permissions and try again.'
        );
        $this->assertDatabaseHas('local_files', ['id' => $file->id]);
    }

    public function updateStoragePost($status = true): TestResponse
    {
        $originalStoragePath = Setting::getStoragePath();
        $response = $this->setStoragePath($this->newStoragePath);
        $response->assertSessionHas('status', $status);
        $response->assertRedirect(route('admin-config', ['setupMode' => true]));
        $this->assertEquals($originalStoragePath, Setting::getStoragePath());
        return $response;
    }

    public function test_update_storage_not_writable_fail()
    {
        $this->fileOptsMock->shouldReceive('isWritable')->with(CONTENT_SUBDIR)->andReturn(false);
        $response = $this->updateStoragePost(false);
        $this->assertSessionHas($response, 'Unable to create storage directory. Check Permissions');
    }

    public function test_update_thumbnail_not_writable_fail()
    {
        $this->fileOptsMock->shouldReceive('isWritable')->with(THUMBS_SUBDIR)->andReturn(false);
        $response = $this->updateStoragePost(false);
        $this->assertSessionHas($response, 'Unable to create thumbnail directory. Check Permissions');
    }

    public function postNewStoragePath(): void
    {
        $response = $this->setStoragePath($this->newStoragePath);
        $response->assertSessionHas('status', true);
        $response->assertRedirect(route('drive'));
        $this->assertEquals($this->getFakeLocalStoragePath($this->newStoragePath), Setting::getStoragePath());
        $this->assertSessionHas($response, 'Storage path updated successfully');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->newStoragePath = '/foo/bar';
        $this->fileOptsMock = Mockery::mock(FileOperationsService::class, [new PathService()])->makePartial();
        $this->app->instance(FileOperationsService::class, $this->fileOptsMock);
        $this->settingMock = Mockery::mock(Setting::class)->makePartial();
        $this->app->instance(Setting::class, $this->settingMock);
        $this->setupStoragePathPost();
    }

    public function test_two_factor_code_enable_is_throttled_after_six_attempts()
    {
        $google2FA = Mockery::mock(Google2FA::class);
        $google2FA->shouldReceive('verify')->andReturn(false);
        $this->app->instance(Google2FA::class, $google2FA);

        // the first five attempts are allowed through to the controller
        for ($i = 0; $i < 5; $i++) {
            $this->post(
                route('admin-config.two-factor-code-enable'),
                [
                    '_token' => csrf_token(),
                    'code' => '000000',
                ]
            );
        }

        // the sixth attempt within the window is throttled (ThrottleException)
        $response = $this->post(
            route('admin-config.two-factor-code-enable'),
            [
                '_token' => csrf_token(),
                'code' => '000000',
            ]
        );
        $response->assertRedirect(
            route('rejected', ['message' => 'Too Many requests. Please try again later'])
        );
    }
}
