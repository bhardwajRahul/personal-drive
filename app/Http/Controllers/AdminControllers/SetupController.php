<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\SetupAccountRequest;
use App\Models\User;
use App\Traits\FlashMessages;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    use FlashMessages;

    public function show(): Response
    {
        return Inertia::render('Admin/Setup');
    }

    public function update(SetupAccountRequest $request): RedirectResponse
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        try {
            $user = User::create(
                [
                'username' => $request->validated('username'),
                'is_admin' => 1,
                'password' => bcrypt($request->validated('password')),
                ]
            );
            Auth::login($user, true);
            return $this->successTo('admin-config', 'Created User successfully', ['setupMode' => true]);
        } catch (Exception) {
            return $this->errorTo('admin-config', 'Error. could not create user. Try re-installing, checking permissions for storage folder', ['setupMode' => true]);
        }
    }
}
