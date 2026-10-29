<?php
namespace Modules\EIS\Services\Http;

use Illuminate\Support\Facades\Http;
use Modules\EIS\Exceptions\EisSaleException;
use Modules\EIS\Services\Security\EisSignatureService;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisHttpClient
{   
    protected string $baseUrl;

    public function __construct(
        protected EisSignatureService $signer,
        protected EisHealthService $health

    ) {
        $this->baseUrl = rtrim(trim((string) config('eis.base_url')), '/');
        if ($this->baseUrl === '') {
            throw new \InvalidArgumentException('Configure EIS_BASE_URL before contacting EIS.');
        }
    }


    public function post($endpoint, $payload, $setting)
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) ($setting->business_id ?? 0));

        if (! $this->health->isOnline($setting->business_id, $setting->jwt_token)) {
            throw new EisSaleException('EIS server is currently offline.');
        }

        $timestamp = time();

        $signature = $this->signer->make(
            $payload,
            $setting->secret_key,
            $timestamp
        );

        return Http::baseUrl($this->baseUrl)
            ->withToken($setting->jwt_token)
            ->withHeaders([
                'X-TPIN' => $setting->tpin,
                'X-SIGNATURE' => $signature,
                'X-TIMESTAMP' => $timestamp,
                'X-DEVICE-ID' => $setting->device_id,
            ])
            ->post($endpoint, $payload)
            ->json();
    }

    public function get($endpoint, $setting)
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) ($setting->business_id ?? 0));

        $timestamp = time();

        $signature = hash_hmac('sha256', $endpoint . $timestamp, $setting->secret_key);

        return Http::baseUrl($this->baseUrl)
            ->withToken($setting->jwt_token)
            ->withHeaders([
                'X-TPIN' => $setting->tpin,
                'X-SIGNATURE' => $signature,
                'X-TIMESTAMP' => $timestamp,
            ])
            ->get($endpoint)
            ->json();
    }
}