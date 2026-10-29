<?php

namespace Modules\EIS\Services\Configuration;

use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Services\Terminal\TerminalContext;

class TerminalConfigurationService
{
    public function sync(EisConfiguration $configuration, array $data): void
    {
        $payload = json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        TerminalContext::assertConfiguration(
            TerminalContext::settings((int) $configuration->business_id, null, (int) $configuration->eis_setting_id),
            $payload
        );
        app(ConfigurationSyncService::class)->syncTerminalConfiguration($configuration, $payload);
    }
}
