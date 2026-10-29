<?php

namespace Modules\EIS\Services\Configuration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Models\EisTerminalConfiguration;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\EisSubscriptionAccess;

class TerminalTokenService
{
    public function __construct(private EisConfigurationClient $client) {}

    public function request(EisSetting $setting, int $actorId): array
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        if (DB::connection()->transactionLevel() !== 0) {
            throw new \InvalidArgumentException('Terminal token requests must run outside an existing database transaction.');
        }
        $endpoint = EisUtilitiesClient::baseUrl();
        [$id, $current] = DB::transaction(function () use ($setting, $actorId, $endpoint) {
            DB::table('business')->where('id', $setting->business_id)->lockForUpdate()->first();
            $current = EisSetting::where('business_id', $setting->business_id)->lockForUpdate()->findOrFail($setting->id);
            if ((int) $current->location_id !== (int) $setting->location_id
                || TerminalContext::site($current) !== TerminalContext::site($setting)
                || $current->device_id !== $setting->device_id || $current->tpin !== $setting->tpin
                || $current->jwt_token !== $setting->jwt_token) {
                throw new \InvalidArgumentException('Terminal identity or credentials changed. Reload before requesting a token.');
            }
            if (strlen(trim((string) $current->jwt_token)) < 20) {
                throw new \InvalidArgumentException('Activate this terminal before requesting a new token.');
            }
            $configuration = TerminalContext::configuration((int) $current->business_id, (int) $current->id);
            if ((int) $configuration->location_id !== (int) $current->location_id
                || !EisTerminalConfiguration::where('configuration_id', $configuration->id)->exists()) {
                throw new \InvalidArgumentException('Refresh this terminal configuration before requesting a new token.');
            }
            $pending = DB::table('eis_terminal_token_requests')->where('eis_setting_id', $current->id)
                ->whereIn('status', ['sending', 'unknown'])->lockForUpdate()->first();
            if ($pending) {
                throw new \InvalidArgumentException('Token request #'.$pending->id.' is unresolved. Verify credentials with MRA; do not resend.');
            }
            $id = DB::table('eis_terminal_token_requests')->insertGetId([
                'business_id' => $current->business_id, 'eis_setting_id' => $current->id,
                'location_id' => $current->location_id, 'created_by' => $actorId, 'endpoint' => $endpoint,
                'status' => 'sending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            return [$id, $current];
        });
        try {
            $response = $this->client->requestNewTerminalToken($current->jwt_token, (int) $current->business_id);
            // Swagger leaves data untyped. Persist only recognized credentials; never guess or expose it.
            $data = $response->data;
            if (!is_string($data->jwtToken ?? null) || strlen(trim($data->jwtToken)) < 20
                || (isset($data->secretKey) && (!is_string($data->secretKey) || trim($data->secretKey) === ''))) {
                throw new \UnexpectedValueException('Unrecognized token response.');
            }
            DB::transaction(function () use ($current, $id, $data) {
                $fresh = EisSetting::where('business_id', $current->business_id)->lockForUpdate()->findOrFail($current->id);
                if ($fresh->jwt_token !== $current->jwt_token || $fresh->device_id !== $current->device_id
                    || $fresh->tpin !== $current->tpin || (int) $fresh->location_id !== (int) $current->location_id
                    || TerminalContext::site($fresh) !== TerminalContext::site($current)) {
                    throw new \RuntimeException('Terminal changed during token request.');
                }
                $credentials = ['jwt_token' => $data->jwtToken];
                if (isset($data->secretKey)) { $credentials['secret_key'] = $data->secretKey; }
                $fresh->update($credentials);
                $configuration = TerminalContext::configuration((int) $fresh->business_id, (int) $fresh->id);
                if ((int) $configuration->location_id !== (int) $fresh->location_id) {
                    throw new \RuntimeException('Terminal configuration ownership changed during token request.');
                }
                EisTerminalConfiguration::where('configuration_id', $configuration->id)->lockForUpdate()->firstOrFail()->update($credentials);
                DB::table('eis_terminal_token_requests')->where('id', $id)->update([
                    'status' => 'accepted', 'result_message' => 'New terminal token stored securely.', 'updated_at' => now(),
                ]);
            });
            return ['success' => true, 'message' => 'New terminal token stored securely.'];
        } catch (\Throwable $exception) {
            Log::error('EIS terminal token request requires verification', [
                'request_id' => $id, 'terminal_id' => $current->id, 'exception' => get_class($exception),
            ]);
            $message = 'Token request #'.$id.' has an uncertain outcome. Credentials were not confirmed locally. Verify with MRA before requesting another token.';
            DB::table('eis_terminal_token_requests')->where('id', $id)->update([
                'status' => 'unknown', 'result_message' => $message, 'updated_at' => now(),
            ]);
            return ['success' => false, 'message' => $message];
        }
    }
}
