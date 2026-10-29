<?php

namespace Modules\EIS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\EIS\Services\EisSubscriptionAccess;

class RequireEisSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user && $request->is('api/*')) {
            $user = auth('api')->user();
        }

        if ($user && $user->can('superadmin')) {
            return $next($request);
        }

        $businessId = $user->business_id
            ?? $request->route('businessId')
            ?? $request->input('business_id');
        abort_unless($businessId, 403, 'EIS access requires a business with an active EIS package subscription.');

        abort_unless(
            app(EisSubscriptionAccess::class)->allows((int) $businessId),
            403,
            'The active package does not include the EIS module.'
        );

        return $next($request);
    }
}
