<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\PersonalDriveExceptions\TwoFactorException;
use Tests\TestCase;

class TwoFactorExceptionTest extends TestCase
{
    public function test_could_not_validate_creates_exception_with_stable_message(): void
    {
        $exception = TwoFactorException::couldNotValidate();

        $this->assertInstanceOf(TwoFactorException::class, $exception);
        $this->assertSame('Could not validate two-factor code', $exception->getMessage());
    }

    public function test_exception_extends_personal_drive_exception(): void
    {
        $exception = TwoFactorException::couldNotValidate();

        $this->assertInstanceOf(\App\Exceptions\PersonalDriveExceptions\PersonalDriveException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(TwoFactorException::class);
        $this->expectExceptionMessage('Could not validate two-factor code');

        throw TwoFactorException::couldNotValidate();
    }
}
