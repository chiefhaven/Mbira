<?php

namespace Modules\EIS\Http\Middleware;

use Closure;

class EnforceTerminalBusiness
{
    public function handle($request, Closure $next)
    {
        // Webhook authentication is a separate EIS machine-to-machine contract.
        if ($request->is('api/v1/webhooks/*')) {
            return $next($request);
        }
        $user = $request->user();
        if (!$user && $request->is('api/*')) {
            $user = auth('api')->user();
            if ($user) {
                auth()->shouldUse('api');
            }
        }
        abort_unless($user, 401, 'EIS access requires an authenticated business account.');
        $businessId = $user->business_id;
        abort_unless($businessId, 403, 'EIS access requires a business terminal.');
        $targets = [$request->route('businessId'), $request->input('business_id')];
        $bulk = $request->input('business_ids', []);
        abort_unless(is_array($bulk), 422, 'Business IDs must be an array.');
        foreach (array_merge($targets, $bulk) as $target) {
            if ($target !== null && (!is_scalar($target) || (string) $target !== (string) $businessId)) {
                abort(403, 'EIS access is limited to your business terminal.');
            }
        }
        if ($request->hasSession() && $request->session()->has('user.business_id')) {
            abort_unless((string) $request->session()->get('user.business_id') === (string) $businessId,
                403, 'EIS session does not match the authenticated business terminal.');
        }
        $locationId = $request->input('location_id');
        $terminalId = $request->route('terminalId') ?? $request->route('terminal') ?? $request->input('terminal_id');
        $token = $request->input('token');
        abort_unless($terminalId === null || (is_scalar($terminalId) && ctype_digit((string) $terminalId) && (int) $terminalId > 0),
            422, 'Select a valid EIS terminal.');
        abort_unless($token === null || is_string($token), 422, 'EIS token must be a string.');
        if ($terminalId === null && $token === null && $request->is('api/*')
            && !$request->is('api/v1/terminal/activate', 'api/v1/terminal/bulk-activate')) {
            try {
                $terminalId = \Modules\EIS\Services\Terminal\TerminalContext::settings((int) $businessId)->id;
            } catch (\InvalidArgumentException $e) {
                abort(422, $e->getMessage());
            }
        }
        if ($terminalId !== null || $token !== null) {
            try {
                $settings = \Modules\EIS\Services\Terminal\TerminalContext::settings(
                    (int) $businessId, $token, $terminalId === null ? null : (int) $terminalId
                );
            } catch (\InvalidArgumentException $e) {
                abort(422, $e->getMessage());
            }
            abort_unless($settings->location_id, 422, 'Reconcile this terminal location before using it.');
            if ($locationId !== null) {
                abort_unless((string) $locationId === (string) $settings->location_id, 403, 'Terminal does not belong to this location.');
            }
            $locationId = $settings->location_id;
        }
        if ($locationId !== null) {
            abort_unless(\App\BusinessLocation::where('business_id', $businessId)->whereKey($locationId)->exists(),
                403, 'Location does not belong to this business.');
            $permitted = $user->permitted_locations();
            abort_unless($permitted === 'all' || in_array((int) $locationId, array_map('intval', $permitted), true),
                403, 'You do not have access to this location.');
        }
        return $next($request);
    }
}
