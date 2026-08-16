<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\AdminConfigService;
use App\Services\FileOperationsService;
use Mockery;
use Tests\TestCase;

class AdminConfigServiceTest extends TestCase
{
    protected $uploadService;
    protected $adminConfigService;
    protected $setting;
    protected $storagePath = '/tmp/test_storage';

    public function test_get_php_upload_max_filesize()
    {
        $this->assertEquals(ini_get('upload_max_filesize'), $this->adminConfigService->getPhpUploadMaxFilesize());
    }

    public function test_get_php_post_max_size()
    {
        $this->assertEquals(ini_get('post_max_size'), $this->adminConfigService->getPhpPostMaxSize());
    }

    public function test_get_php_max_file_uploads()
    {
        $this->assertEquals(ini_get('max_file_uploads'), $this->adminConfigService->getPhpMaxFileUploads());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadService = Mockery::mock(FileOperationsService::class);
        $this->setting = Mockery::mock(Setting::class);
        $this->adminConfigService = Mockery::mock(
            AdminConfigService::class,
            [ $this->uploadService, $this->setting]
        )
            ->makePartial();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_storage_path_catch_returns_stable_error_message(): void
    {
        $exception = new \Exception('disk failure');

        $this->setting
            ->shouldReceive('updateStoragePath')
            ->once()
            ->andThrow($exception);

        $result = $this->adminConfigService->updateStoragePath('/tmp/storage');

        $this->assertFalse($result['status']);
        $this->assertSame('Unable to update storage path', $result['message']);
    }

    public function test_update_storage_path_does_not_expose_dependency_exception_message(): void
    {
        $this->setting
            ->shouldReceive('updateStoragePath')
            ->once()
            ->andThrow(new \RuntimeException('Cannot access /srv/private/storage'));

        $result = $this->adminConfigService->updateStoragePath('/tmp/storage');

        $this->assertFalse($result['status']);
        $this->assertSame('Unable to update storage path', $result['message']);
    }

    public function test_update_storage_path_catch_returns_false_status(): void
    {
        $this->setting
            ->shouldReceive('updateStoragePath')
            ->once()
            ->andThrow(new \Exception('any error'));

        $result = $this->adminConfigService->updateStoragePath('/tmp/storage');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['status']);
    }

}
