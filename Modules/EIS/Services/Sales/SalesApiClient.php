<?php

namespace Modules\EIS\Services\Sales;

use Illuminate\Support\Facades\Http;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\EisSubscriptionAccess;

class SalesApiClient
{
    public function send(EisSetting $setting, string $operation, array $payload = []): array
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        if (!in_array($operation, ['last-submitted-online-transaction', 'last-submitted-offline-transaction',
            'get-invoice-by-number', 'process-credit-debit-note'], true)) {
            throw new \InvalidArgumentException('Select a documented Sales operation.');
        }
        if (trim((string) $setting->jwt_token) === '') {
            throw new \InvalidArgumentException('Activate the selected terminal first.');
        }
        $request = Http::withToken($setting->jwt_token)->acceptJson()->asJson()->connectTimeout(10)
            ->timeout(60)->withOptions(['allow_redirects' => false]);
        $url = EisUtilitiesClient::baseUrl().'/sales/'.$operation;
        $response = $payload ? $request->post($url, $payload) : $request->send('POST', $url);
        $body = $response->json();
        $outcome = 'unknown';
        if (is_array($body) && !$response->serverError()) {
            if ($response->successful() && ($body['statusCode'] ?? null) === 1
                && empty($body['errors']) && is_array($body['data'] ?? null)) {
                $outcome = 'accepted';
            } elseif ($response->clientError() || ($body['statusCode'] ?? null) === 0) {
                $outcome = 'rejected';
            }
        }
        return ['outcome' => $outcome, 'http_status' => $response->status(),
            'response' => is_array($body) ? array_intersect_key($body, array_flip(['statusCode', 'remark', 'errors', 'data'])) : []];
    }
}
