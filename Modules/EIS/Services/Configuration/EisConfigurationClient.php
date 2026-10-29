<?php

namespace Modules\EIS\Services\Configuration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisConfigurationClient
{
    public function latest(string $token, int $businessId): EISConfigurationResponse
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);
        $this->validateToken($token);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = $this->request($token, 'get-latest-configs');
            } catch (ConnectionException $exception) {
                Log::warning('EIS configuration connection failed', ['attempt' => $attempt]);
                if ($attempt === 3) {
                    throw new \RuntimeException('EIS configuration could not be reached after three attempts.');
                }
                $this->backoff($attempt);
                continue;
            }
            if ($response->serverError() && $attempt < 3) {
                Log::warning('EIS configuration server error', ['attempt' => $attempt, 'http_status' => $response->status()]);
                $this->backoff($attempt);
                continue;
            }
            $body = $this->envelope($response);
            $configuration = new EISConfigurationResponse($body);
            if (!$configuration->hasCompleteData() || !is_object($body->data->globalConfiguration)
                || !is_object($body->data->terminalConfiguration) || !is_object($body->data->taxpayerConfiguration)) {
                throw new \UnexpectedValueException('EIS did not return all three configuration groups.');
            }
            return $configuration;
        }
        throw new \RuntimeException('EIS configuration request failed.');
    }

    public function requestNewTerminalToken(string $token, int $businessId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);
        $this->validateToken($token);
        // This endpoint rotates credentials: never retry, including after a lost response.
        return $this->envelope($this->request($token, 'request-new-terminal-token'));
    }

    private function request(string $token, string $operation): Response
    {
        return Http::withToken($token)->acceptJson()->connectTimeout(10)->timeout(60)
            ->withOptions(['allow_redirects' => false])
            ->send('POST', EisUtilitiesClient::baseUrl().'/configuration/'.$operation);
    }

    private function envelope(Response $response): object
    {
        $body = $response->object();
        if (!$response->successful() || !is_object($body) || ($body->statusCode ?? null) !== 1
            || !empty($body->errors) || (isset($body->errors) && !is_array($body->errors))
            || (isset($body->remark) && !is_string($body->remark)) || !is_object($body->data ?? null)) {
            Log::warning('EIS configuration response rejected', ['http_status' => $response->status()]);
            throw new \UnexpectedValueException('EIS returned an unsuccessful or invalid configuration response (HTTP '.$response->status().').');
        }
        return $body;
    }

    private function validateToken(string $token): void
    {
        if (strlen(trim($token)) < 20) {
            throw new \InvalidArgumentException('Configure a valid terminal authentication token first.');
        }
    }

    protected function backoff(int $attempt): void
    {
        sleep(2 ** ($attempt - 1));
    }
}
