<?php

namespace Modules\EIS\Services\Configuration;

use Modules\EIS\Models\EisConfiguration;

class ConfigurationResolver
{

    public function get(int $businessId, ?int $terminalId = null)
    {
        $settings = \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId, null, $terminalId);
        return EisConfiguration::where('eis_setting_id', $settings->id)
        ->latest()
        ->first();
    }


    public function sellerTin(int $businessId, ?int $terminalId = null)
    {
        return $this->get($businessId, $terminalId)
            ?->tpin;
    }


    public function siteId(int $businessId, ?int $terminalId = null)
    {
        return \Modules\EIS\Services\Terminal\TerminalContext::site(
            \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId, null, $terminalId)
        );
    }


    public function vatRegistered(int $businessId, ?int $terminalId = null)
    {
        return $this->get($businessId, $terminalId)
            ?->is_vat_registered;
    }


    public function taxRates(int $businessId, ?int $terminalId = null)
    {
        return $this->get($businessId, $terminalId)
            ?->taxRates;
    }

}