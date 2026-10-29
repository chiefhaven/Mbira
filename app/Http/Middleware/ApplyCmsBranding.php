<?php

namespace App\Http\Middleware;

use App\Services\CmsBranding;
use Closure;
use Illuminate\Support\Facades\Schema;

class ApplyCmsBranding
{
    public function handle($request, Closure $next)
    {
        if (isAppInstalled() && Schema::hasTable('system')) {
            (new CmsBranding())->apply();
        }

        return $next($request);
    }
}
