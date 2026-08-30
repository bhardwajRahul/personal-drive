<?php

namespace App\Http\Controllers\AuthControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\TwoFactorCodeCheckRequest;
use App\Services\TwoFactorService;
use App\Traits\FlashMessages;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    use FlashMessages;

    protected TwoFactorService $twoFactorService;

    public function __construct(
        TwoFactorService $twoFactorService
    ) {
        $this->twoFactorService = $twoFactorService;
    }
    public function index(Request $request)
    {
        return Inertia::render('Auth/TwoFactor');
    }

    public function store(TwoFactorCodeCheckRequest $request)
    {
        $userId = $request->session()->get('twoFactorUserId');
        $user = User::findOrFail($userId);

        $throttleKey = 'two-factor:' . $userId;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->error('Too many attempts. Please try again in ' . $seconds . ' seconds.');
        }

        $twoFactorCode = $request->validated('code');
        $twoFactorSecret = $user->getTwoFactorSecret();
        $isVerified = $this->twoFactorService->twoFactorCodeCheck($twoFactorCode, $twoFactorSecret);

        if ($isVerified) {
            RateLimiter::clear($throttleKey);
            $request->session()->forget('twoFactorUserId');
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended(route('drive', absolute: false));
        }

        RateLimiter::hit($throttleKey);

        return $this->error('Incorrect OTP. Please try again');
    }
}
