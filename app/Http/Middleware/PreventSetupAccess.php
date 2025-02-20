<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class PreventSetupAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('migrations')) {
            return redirect('/')->with('message', 'Setup already completed.');
        }

        return $next($request);
    }
}
