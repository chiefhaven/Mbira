<?php

namespace Modules\EIS\Services\Terminal;

use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Products\OpeningStockInitializer;

final class TerminalContext
{
    public static function configuration(int $businessId, ?int $terminalId = null): \Modules\EIS\Models\EisConfiguration
    {
        $settings = self::settings($businessId, null, $terminalId);
        return \Modules\EIS\Models\EisConfiguration::where('business_id', $businessId)
            ->where('eis_setting_id', $settings->id)->firstOrFail();
    }

    public static function site(object $settings): string
    {
        $value = $settings->site_id ?? $settings->branch_id ?? null;
        $site = OpeningStockInitializer::siteId(['siteId' => $value]);
        if ($site === null || $site !== (string) $value) {
            throw new \InvalidArgumentException('EIS terminal requires an exact configured site ID.');
        }
        return $site;
    }

    public static function assertBusiness(object $settings, int $businessId): void
    {
        if ($businessId <= 0 || (string) ($settings->business_id ?? '') !== (string) $businessId) {
            throw new \InvalidArgumentException('EIS settings do not belong to this business terminal.');
        }
    }

    public static function settings(int $businessId, ?string $token = null, ?int $terminalId = null, ?int $locationId = null): EisSetting
    {
        $query = EisSetting::where('business_id', $businessId);
        if ($terminalId !== null) {
            $query->whereKey($terminalId);
        }
        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }
        if ($token !== null) {
            $query->where('jwt_token', $token);
        }
        $rows = $query->limit(2)->get();
        if ($rows->count() !== 1) {
            throw new \InvalidArgumentException('Select exactly one EIS terminal belonging to this business and location.');
        }
        $settings = $rows->first();
        if ($token !== null && (!is_string($settings->jwt_token) || !hash_equals($settings->jwt_token, $token))) {
            throw new \InvalidArgumentException('EIS credentials do not belong to this business terminal.');
        }
        return $settings;
    }

    public static function forLocation(int $businessId, int $locationId, bool $requireActive = false): EisSetting
    {
        $location = \App\BusinessLocation::where('business_id', $businessId)->findOrFail($locationId);
        if (!$location->default_eis_setting_id) {
            throw new \InvalidArgumentException('Select a default EIS terminal for the sale location.');
        }
        $settings = self::settings($businessId, null, (int) $location->default_eis_setting_id, $locationId);
        if ($requireActive) {
            self::assertActive($settings);
        }
        return $settings;
    }

    public static function isOperational(bool $locallyEnabled, ?bool $remoteActive): bool
    {
        // Development EIS can report working terminals as inactive. Never bypass local deactivation.
        $development = in_array(strtolower(trim((string) config('eis.environment', ''))), ['development', 'dev', 'sandbox'], true);

        return $locallyEnabled && $remoteActive !== null && ($remoteActive || $development);
    }

    public static function assertActive(EisSetting $settings): void
    {
        $configuration = \Modules\EIS\Models\EisConfiguration::where('eis_setting_id', $settings->id)
            ->where('business_id', $settings->business_id)->firstOrFail();
        $terminal = \Modules\EIS\Models\EisTerminalConfiguration::where('configuration_id', $configuration->id)->firstOrFail();
        if (!self::isOperational((bool) $settings->status, $terminal->is_active)) {
            throw new \InvalidArgumentException('The location default EIS terminal is inactive. Activate it before submitting sales.');
        }
    }

    public static function forSale(\App\Transaction $transaction): EisSetting
    {
        $sale = \Modules\EIS\Models\EisSale::where('business_id', $transaction->business_id)
            ->where('transaction_id', $transaction->id)->first();
        if ($sale && !$sale->eis_setting_id) {
            throw new \InvalidArgumentException('This historical EIS sale requires terminal reconciliation before retrying.');
        }
        if ($sale && (int) $sale->location_id !== (int) $transaction->location_id) {
            throw new \InvalidArgumentException('The sale location no longer matches its recorded fiscal location.');
        }
        $terminalId = $sale?->eis_setting_id;
        if (!$sale) {
            $failed = \Illuminate\Support\Facades\DB::table('eis_failed_transactions')
                ->where('business_id', $transaction->business_id)->where('transaction_id', $transaction->id)->get();
            $terminalIds = $failed->pluck('eis_setting_id')->filter()->unique();
            if ($terminalIds->count() > 1) {
                throw new \InvalidArgumentException('Conflicting retry terminals require reconciliation.');
            }
            $terminalId = $terminalIds->first();
            if (!$terminalId && $failed->isNotEmpty()
                && \Illuminate\Support\Facades\DB::table('eis_migration_issues')
                    ->where('source_table', 'eis_failed_transactions')->whereIn('source_id', $failed->pluck('id'))->exists()) {
                throw new \InvalidArgumentException('This historical EIS retry requires terminal reconciliation before submission.');
            }
        }
        $settings = $terminalId
            ? self::settings((int) $transaction->business_id, null, (int) $terminalId)
            : ($transaction->connector_till_id
                ? \Modules\Connector\Support\TillAssignment::terminal((int) $transaction->business_id, (int) $transaction->location_id, (int) $transaction->connector_till_id)
                : self::forLocation((int) $transaction->business_id, (int) $transaction->location_id));
        if ((int) $settings->location_id !== (int) $transaction->location_id) {
            throw new \InvalidArgumentException('The recorded terminal has moved to another EIS site. Reconcile this historical submission without changing its original fiscal location.');
        }
        self::assertActive($settings);
        return $settings;
    }

    public static function assertConfiguration(object $settings, object $data): void
    {
        $site = $data->terminalConfiguration->terminalSite->siteId ?? null;
        $tin = $data->taxpayerConfiguration->tin ?? null;
        if ((!is_string($site) && !is_int($site)) || (string) $site !== self::site($settings)
            || (!is_string($tin) && !is_int($tin)) || (string) $tin !== (string) $settings->tpin) {
            throw new \InvalidArgumentException('EIS configuration does not match the business terminal site and TIN.');
        }
        $terminalId = $data->terminalConfiguration->terminalId ?? null;
        if ($terminalId !== null && (string) $terminalId !== (string) $settings->device_id) {
            throw new \InvalidArgumentException('EIS configuration belongs to a different terminal.');
        }
    }
}
