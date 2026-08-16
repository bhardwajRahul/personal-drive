<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;

class ExceptionRenderingTest extends BaseFeatureTest
{
    public function test_unhandled_exception_does_not_flash_raw_exception_message(): void
    {
        $sensitivePath = '/srv/private/app-secret';
        $sensitiveExceptionMessage = 'Unable to read '.$sensitivePath;

        Route::middleware('web')->get('/_test/unhandled-exception', function () use ($sensitiveExceptionMessage): never {
            throw new RuntimeException($sensitiveExceptionMessage);
        });

        $this->makeUser();
        $this->withExceptionHandling();

        $response = $this->get('/_test/unhandled-exception');

        $response->assertSessionHas('message', 'Something went wrong');

        $flashMessage = session()->get('message');
        $this->assertIsString($flashMessage);
        $this->assertStringNotContainsString($sensitivePath, $flashMessage);
    }
}
