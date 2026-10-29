<?php

namespace Modules\Connector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Connector\Support\TillAssignment;

class ConnectorTill
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasHeader('X-POS-Till')) {
            $value = $request->header('X-POS-Till');
            if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['till_id' => 'Provide a valid till ID.']);
            }
            $till = TillAssignment::validate($request->user(), (int) $value);
            $locations = [];
            if ($request->has('location_id')) {
                $locations[] = $request->input('location_id');
            }
            $sales = $request->input('sells', []);
            if (!is_array($sales)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['sells' => 'Provide a list of sales.']);
            }
            foreach ($sales as $sale) {
                $locations[] = $sale['location_id'] ?? null;
            }
            if ($request->is('connector/api/sell/*', 'connector/api/restaurant/*/*')) {
                $transactionId = $request->route('id') ?? $request->route('sell');
                if ($transactionId !== null) {
                    $transaction = \Illuminate\Support\Facades\DB::table('transactions')
                        ->where('business_id', $request->user()->business_id)->where('id', $transactionId)->first();
                    abort_unless($transaction, 404);
                    $locations[] = $transaction->location_id;
                }
            }
            foreach ($locations as $location) {
                if ((string) $location !== (string) $till->location_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['till_id' => 'The selected till belongs to a different location.']);
                }
            }
            $request->attributes->set('connector_till_id', (int) $till->id);
        }
        return $next($request);
    }
}
