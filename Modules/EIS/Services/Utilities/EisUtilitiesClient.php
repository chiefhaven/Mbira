<?php

namespace Modules\EIS\Services\Utilities;

use Illuminate\Support\Facades\Http;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisUtilitiesClient
{
    public function send(EisSetting $setting, string $operation, array $payload): array
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        $path = UtilityOperations::path($operation);
        $base = self::baseUrl();
        if (trim((string) $setting->jwt_token) === '') {
            throw new \InvalidArgumentException('Configure the EIS endpoint and selected terminal token first.');
        }
        $request = Http::acceptJson()->asJson()->withToken($setting->jwt_token)
            ->connectTimeout(10)->timeout(max(10, (int) config('eis.timeout', 30)))->withOptions(['allow_redirects' => false]);
        $response = $payload === []
            ? $request->send('POST', $base.'/'.$path)
            : $request->post($base.'/'.$path, $payload);
        $body = $response->json();
        $outcome = 'unknown';
        if (is_array($body) && !$response->serverError()) {
            if ($response->successful() && ($body['statusCode'] ?? null) === 1
                && empty($body['errors']) && array_key_exists('data', $body)) {
                $outcome = 'accepted';
            } elseif ($response->clientError() || ($body['statusCode'] ?? null) === 0) {
                $outcome = 'rejected';
            }
        }
        return [
            'outcome' => $outcome, 'http_status' => $response->status(),
            'response' => is_array($body) ? array_intersect_key($body, array_flip(['statusCode', 'remark', 'errors', 'data'])) : [],
        ];
    }

    public static function baseUrl(): string
    {
        $base = rtrim(trim((string) config('eis.base_url')), '/');
        if ($base === '') { throw new \InvalidArgumentException('Configure the EIS endpoint first.'); }
        return preg_replace('~/api/v1$~', '', $base).'/api/v1';
    }
}
