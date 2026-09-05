<?php

namespace App\Traits;

use Illuminate\Http\RedirectResponse;

trait FlashMessages
{
    public function success(string $message, array $moreInfo = []): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status', true);
        if ($moreInfo) {
            session()->flash('more_info', $moreInfo);
        }

        return redirect()->back();
    }

    public function successTo(string $route, string $message, array $params = []): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status', true);

        return redirect()->route($route, $params);
    }

    public function error(string $message): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status', false);

        return redirect()->back();
    }

    public function errorTo(string $route, string $message, array $params = []): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status', false);

        return redirect()->route($route, $params);
    }
}
