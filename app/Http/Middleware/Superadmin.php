<?php

namespace App\Http\Middleware;

use Closure;

class Superadmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $administrator_list = config('constants.administrator_usernames');
        $administrator_usernames = collect(explode(',', (string) $administrator_list))
            ->map(function ($username) {
                return trim(strtolower($username));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($request->user()) && in_array(strtolower($request->user()->username), $administrator_usernames, true)) {
            return $next($request);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }
}
