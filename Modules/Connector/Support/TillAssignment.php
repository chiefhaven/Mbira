<?php

namespace Modules\Connector\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;

final class TillAssignment
{
    public static function validate($user, int $tillId, ?int $locationId = null): object
    {
        $till = DB::table('connector_tills')->where('business_id', $user->business_id)->where('id', $tillId)->first();
        $permitted = $user->permitted_locations((int) $user->business_id);
        if (!$till || !$till->is_active
            || ($locationId !== null && (int) $till->location_id !== $locationId)
            || ($permitted !== 'all' && !in_array((int) $till->location_id, $permitted))
            || !\App\BusinessLocation::where('business_id', $user->business_id)->whereKey($till->location_id)->exists()) {
            throw ValidationException::withMessages(['till_id' => 'Select an active till at a location permitted for this account and sale.']);
        }
        return $till;
    }

    public static function bind($user, int $transactionId, ?int $tillId): void
    {
        if ($tillId === null) {
            return;
        }
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Till assignment must be saved inside the sale or payment transaction.');
        }
        $sale = DB::table('transactions')->where('business_id', $user->business_id)
            ->where('id', $transactionId)->lockForUpdate()->first();
        abort_unless($sale, 404);
        self::validate($user, $tillId, (int) $sale->location_id);
        // Once fiscal tracking exists, neither checkout nor device changes may reroute it.
        if (DB::table('eis_sales')->where('transaction_id', $transactionId)->exists()
            || DB::table('eis_failed_transactions')->where('transaction_id', $transactionId)->exists()) {
            return;
        }
        DB::table('transactions')->where('id', $transactionId)->update(['connector_till_id' => $tillId]);
    }

    public static function terminal(int $businessId, int $locationId, int $tillId): EisSetting
    {
        $till = DB::table('connector_tills')->where('business_id', $businessId)->where('location_id', $locationId)
            ->where('id', $tillId)->first();
        if (!$till) {
            throw new \InvalidArgumentException('The sale till no longer belongs to its original business location.');
        }
        if ($till->is_active && $till->eis_setting_id) {
            $setting = EisSetting::where('business_id', $businessId)->where('location_id', $locationId)
                ->find($till->eis_setting_id);
            if ($setting) {
                $configuration = \Modules\EIS\Models\EisConfiguration::where('business_id', $businessId)
                    ->where('location_id', $locationId)->where('eis_setting_id', $setting->id)->first();
                $terminal = $configuration
                    ? \Modules\EIS\Models\EisTerminalConfiguration::where('configuration_id', $configuration->id)->first()
                    : null;
                if (TerminalContext::isOperational((bool) $setting->status, $terminal?->is_active)) {
                    return $setting;
                }
            }
        }
        \Illuminate\Support\Facades\Log::notice('Till terminal unavailable; using location default', [
            'business_id' => $businessId, 'location_id' => $locationId, 'till_id' => $tillId,
        ]);
        return TerminalContext::forLocation($businessId, $locationId, true);
    }
}
