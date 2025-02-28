<?php

namespace App\Http\Controllers\ShareControllers;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use App\Http\Requests\DriveRequests\ShareFilesGuestPasswordRequest;
use App\Http\Requests\DriveRequests\ShareFilesGuestRequest;
use App\Models\LocalFile;
use App\Models\Share;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class ShareFilesGuestController
{
    public function index(ShareFilesGuestRequest $request): Response
    {
        $slug = $request->validated('slug');
        $path = $request->validated('path');
        $share = Share::whereBySlug($slug)->first();

        if (! $share) {
            throw ShareFileException::couldNotShare();
        }

        if ($path) {
            $files = Share::getFilenamesByPath($share->id, ($share->public_path ? $share->public_path.DIRECTORY_SEPARATOR : '').$path);
        } else {
            $files = Share::getFilenamesBySlug($slug);
        }
        $files = LocalFile::modifyFileCollectionForGuest($files, $share->public_path);

        return Inertia::render('Drive/ShareFilesGuestHome', [
            'files' => $files,
            'path' => '/shared/'.$slug.($path ? '/'.$path : ''),
            'token' => csrf_token(),
            'guest' => 'on',
            'slug' => $slug,
        ]);
    }

    public function passwordPage(ShareFilesGuestRequest $request): Response
    {
        $slug = $request->validated('slug');

        return Inertia('Drive/Shares/CheckSharePassword', ['slug' => $slug]);
    }

    public function checkPassword(ShareFilesGuestPasswordRequest $request): RedirectResponse
    {
        $slug = $request->validated('slug');
        $password = $request->validated('password');
        $share = Share::whereBySlug($slug)->first();

        if (! $share) {
            throw ShareFileException::couldNotShare();
        }

        if (Hash::check($password, $share->password)) {
            // Set session authenticated key
            Session::put("shared_{$slug}_authenticated", true);

            return redirect("/shared/$slug");
        }

        throw ShareFileException::shareWrongPassword();
    }
}
