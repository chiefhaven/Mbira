<?php

namespace Modules\EIS\Services\Http;

use Modules\EIS\Services\EisSubscriptionAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EisHealthService
{
    public function isOnline(int $businessId, string $token): bool
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        $base = rtrim(trim((string) config('eis.base_url')), '/');
        // Deployments configure either the host or the complete API base.
        $base = preg_replace('~/api/v1$~', '', $base);
        $token = trim($token);
        if ($base === '' || $token === '') {
            return false;
        }

        $url = $base.'/api/v1/utilities/ping';
        // Ignore legacy eight-hour entries; credential/host changes must recheck.
        $cacheKey = "eis.health.v2.{$businessId}";
        $identity = hash('sha256', $url."\0".$token);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['identity'] ?? null) === $identity
            && is_bool($cached['online'] ?? null)) {
            return $cached['online'];
        }

        $connectTimeout = max(1, min(20, (int) config('eis.health_connect_timeout', 20)));
        $timeout = max($connectTimeout, min(20, (int) config('eis.health_timeout', 20)));
        try {
            $response = Http::acceptJson()->withToken($token)
            ->connectTimeout($connectTimeout)->timeout($timeout)
                ->withOptions(['allow_redirects' => false])
                ->post($url);
            $body = $response->json();
            $online = $response->successful() && is_array($body)
                && ($body['statusCode'] ?? null) === 1
                && empty($body['errors'])
                && is_array($body['data'] ?? null)
                && is_string($body['data']['serverDate'] ?? null)
                && $body['data']['serverDate'] !== '';
        } catch (\Throwable $e) {
            // Do not log credentials, response bodies, or request exception text.
            $errno = null;
            for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                if ($cause instanceof \GuzzleHttp\Exception\ConnectException
                    || $cause instanceof \GuzzleHttp\Exception\RequestException) {
                    $context = $cause->getHandlerContext();
                    $errno = isset($context['errno']) ? (int) $context['errno'] : null;
                    break;
                }
            }
            Log::warning('EIS health check failed', [
                'business_id' => $businessId,
                'exception' => get_class($e),
                'host' => parse_url($url, PHP_URL_HOST),
                'curl_errno' => $errno,
                'connection_reason' => match ($errno) {
                    5 => 'proxy_dns_failed',
                    6 => 'host_dns_failed',
                    7 => 'connection_failed',
                    28 => 'timeout',
                    35 => 'tls_handshake_failed',
                    51, 60 => 'tls_certificate_failed',
                    77 => 'tls_ca_bundle_invalid',
                    default => 'unknown_connection_error',
                },
                'connect_timeout_seconds' => $connectTimeout,
                'timeout_seconds' => $timeout,
            ]);
            $online = false;
        }

        Cache::put($cacheKey, ['identity' => $identity, 'online' => $online], $online ? 60 : 15);

        return $online;
    }


    public function clearCache(int $businessId): void
    {
        Cache::forget("eis.health.v2.{$businessId}");
        Cache::forget("eis.health.{$businessId}");
        Cache::forget('eis_status_'.$businessId);
    }
}