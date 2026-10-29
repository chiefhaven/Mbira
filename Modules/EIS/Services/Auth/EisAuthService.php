<?php
namespace Modules\EIS\Services\Auth;

use Modules\EIS\Models\EisSetting;
use Illuminate\Support\Facades\Http;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisAuthService
{
    public function refresh(EisSetting $setting)
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        $baseUrl = rtrim(trim((string) config('eis.base_url')), '/');
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('Configure EIS_BASE_URL before refreshing EIS credentials.');
        }
        $response = Http::post($baseUrl . '/auth/refresh', [
            'refresh_token' => $setting->refresh_token,
        ]);

        $data = $response->json();

        $setting->update([
            'jwt_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => now()->addMinutes(55),
        ]);

        return $setting;
    }
}