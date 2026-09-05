<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaMiddleware extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $flashA = [
            'message' => $request->session()->get('message'),
            'status' => $request->session()->get('status'),
            'shared_link' => $request->session()->get('shared_link'),
            'more_info' => $request->session()->get('more_info'),
        ];

        return [
            ...parent::share($request),
            'flash' => $flashA,
        ];
    }
}
