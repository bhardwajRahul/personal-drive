<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateShareRequest;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shares = Share::getAllUnExpired();

        return response()->json(['shares' => $shares]);
    }

    public function store(CreateShareRequest $request): JsonResponse
    {
        $fileIds = $request->validated('fileList');
        $slug = $request->validated('slug', '') ?: Str::random(10);
        $password = $request->validated('password', '');
        $expiry = $request->validated('expiry', '');

        $localFiles = LocalFile::whereIn('id', $fileIds)->get();

        if ($localFiles->count() !== count($fileIds)) {
            return response()->json(['message' => 'Some files not found'], 422);
        }

        $hashedPassword = $password ? Hash::make($password) : '';

        $share = Share::add($slug, $hashedPassword, $expiry, $localFiles->first()->public_path);
        SharedFile::addArray($localFiles, $share->id);

        return response()->json([
            'share' => $share,
            'url' => url('/shared/' . $slug),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        Share::whereById($id)->delete();

        return response()->json(['message' => 'Share deleted']);
    }

    public function toggle(Request $request, string $id): JsonResponse
    {
        $share = Share::whereById($id)->first();

        if (!$share) {
            return response()->json(['message' => 'Share not found'], 404);
        }

        $share->forceFill(['enabled' => !$share->enabled])->save();

        return response()->json([
            'share' => $share->fresh(),
            'message' => $share->fresh()->enabled ? 'Share enabled' : 'Share paused',
        ]);
    }
}
