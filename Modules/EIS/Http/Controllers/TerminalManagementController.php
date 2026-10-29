<?php

namespace Modules\EIS\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Configuration\TerminalTokenService;
use Modules\EIS\Services\Terminal\EisTerminalActivationService;
use Modules\EIS\Services\Terminal\TerminalContext;

class TerminalManagementController extends Controller
{
    public function __construct(protected EisTerminalActivationService $activationService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $locations = $this->locations($request, $businessId)
            ->orderBy('name')->get(['id', 'name', 'default_eis_setting_id']);
        $terminals = EisSetting::query()
            ->where('eis_settings.business_id', $businessId)
            ->whereIn('eis_settings.location_id', $locations->pluck('id'))
            ->leftJoin('eis_configurations as configuration', function ($join) {
                $join->on('configuration.eis_setting_id', '=', 'eis_settings.id')
                    ->on('configuration.business_id', '=', 'eis_settings.business_id')
                    ->on('configuration.location_id', '=', 'eis_settings.location_id');
            })
            ->leftJoin('eis_terminal_configurations as terminal', 'terminal.configuration_id', '=', 'configuration.id')
            ->orderBy('eis_settings.id')
            ->get([
                'eis_settings.id', 'eis_settings.location_id', 'eis_settings.status',
                'eis_settings.device_id', 'terminal.terminal_label', 'terminal.is_active',
            ])
            ->map(function ($terminal) use ($locations) {
                $location = $locations->firstWhere('id', $terminal->location_id);

                return [
                    'id' => (int) $terminal->id,
                    'location_id' => (int) $terminal->location_id,
                    'location_name' => $location->name,
                    'terminal_label' => $terminal->terminal_label,
                    'device_id' => $terminal->device_id,
                    'is_active' => TerminalContext::isOperational((bool) $terminal->status, $terminal->is_active),
                    'is_default' => (int) $location->default_eis_setting_id === (int) $terminal->id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => ['terminals' => $terminals, 'locations' => $locations],
        ]);
    }

    public function page(Request $request)
    {
        $this->businessId($request);

        return view('eis::terminals.index');
    }

    public function activate(Request $request)
    {
        $businessId = $this->businessId($request);
        $input = $request->validate([
            'terminal_activation_code' => 'required|string|max:255',
            'environment' => 'sometimes|array',
            'environment.platform' => 'sometimes|array',
            'environment.pos' => 'sometimes|array',
        ]);

        return $this->result($this->activationService->activateTerminal(
            $businessId,
            $input['terminal_activation_code'],
            $input['environment'] ?? [],
            (int) $request->user()->id
        ));
    }

    public function latestConfig(Request $request, int $terminalId)
    {
        $terminal = $this->terminal($request, $terminalId);

        $result = $this->activationService->getLatestConfig((int) $terminal->business_id, $terminalId);
        if (empty($result['success'])) { return $this->result($result); }
        return response()->json([
            'success' => true, 'message' => $result['message'] ?? $result['msg'] ?? 'Latest configuration fetched.',
            'data' => $this->configurationData($terminal),
        ]);
    }

    public function configuration(Request $request, int $terminalId)
    {
        return response()->json(['success' => true, 'data' => $this->configurationData($this->terminal($request, $terminalId))]);
    }

    public function requestNewToken(Request $request, int $terminalId, TerminalTokenService $tokens)
    {
        $terminal = $this->terminal($request, $terminalId);
        $request->validate(['confirmed' => 'required|accepted']);
        try {
            return $this->result($tokens->request($terminal, (int) $request->user()->id));
        } catch (\InvalidArgumentException $exception) {
            \Illuminate\Support\Facades\Log::warning('EIS terminal token request blocked', ['terminal_id' => $terminalId]);
            return $this->result(['success' => false, 'message' => $exception->getMessage()]);
        }
    }

    private function configurationData(EisSetting $setting): array
    {
        $configuration = TerminalContext::configuration((int) $setting->business_id, (int) $setting->id);
        abort_unless($configuration, 404, 'Refresh the latest configuration for this terminal first.');
        $raw = $configuration->raw_response;
        if (is_string($raw)) { $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        $data = $raw['data'] ?? null;
        abort_unless(is_array($data), 409, 'Stored configuration is incomplete. Fetch the latest configuration.');
        $groups = [
            'globalConfiguration' => ['id', 'versionNo', 'taxrates'],
            'terminalConfiguration' => ['versionNo', 'terminalLabel', 'isActiveTerminal', 'emailAddress',
                'phoneNumber', 'tradingName', 'addressLines', 'terminalSite', 'offlineLimit'],
            'taxpayerConfiguration' => ['versionNo', 'tin', 'isVATRegistered', 'taxOfficeCode', 'taxOffice',
                'activatedTaxRateIds', 'activatedTaxrates', 'activatedLevies'],
        ];
        $result = ['terminal_id' => $setting->id, 'location_id' => $setting->location_id,
            'last_synced_at' => $configuration->last_synced_at];
        foreach ($groups as $name => $keys) {
            abort_unless(isset($data[$name]) && is_array($data[$name]), 409, 'Stored configuration is incomplete. Fetch the latest configuration.');
            $result[$name] = array_intersect_key($data[$name], array_flip($keys));
        }
        foreach ([
            ['globalConfiguration', 'taxrates', ['id', 'name', 'chargeMode', 'ordinal', 'rate'], true],
            ['terminalConfiguration', 'terminalSite', ['siteId', 'siteName'], false],
            ['terminalConfiguration', 'offlineLimit', ['maxTransactionAgeInHours', 'maxCummulativeAmount'], false],
            ['taxpayerConfiguration', 'taxOffice', ['code', 'name'], false],
            ['taxpayerConfiguration', 'activatedTaxrates', ['id', 'taxpayerConfigurationId', 'taxRateId'], true],
            ['taxpayerConfiguration', 'activatedLevies', ['id', 'name', 'chargeMode', 'rate', 'isActive'], true],
        ] as [$group, $field, $keys, $list]) {
            if (!isset($result[$group][$field])) { continue; }
            $filter = fn (array $item) => array_intersect_key($item, array_flip($keys));
            $result[$group][$field] = $list ? array_map($filter, $result[$group][$field]) : $filter($result[$group][$field]);
        }
        return $result;
    }

    public function deactivate(Request $request, int $terminalId)
    {
        $terminal = $this->terminal($request, $terminalId);
        $input = $request->validate(['reason' => 'nullable|string|max:1000']);

        return $this->result($this->activationService->deactivate(
            (int) $terminal->business_id,
            $input['reason'] ?? null,
            (int) $request->user()->id,
            $terminalId
        ));
    }

    public function renewTokens(Request $request, int $terminalId)
    {
        $terminal = $this->terminal($request, $terminalId);
        if (empty($terminal->jwt_token)) {
            return $this->result(['success' => false, 'message' => 'Activate this terminal before renewing its tokens.']);
        }

        return $this->result($this->activationService->regenerateCredentials(
            (int) $terminal->business_id, $terminal->jwt_token, $terminalId
        ));
    }

    public function setDefault(Request $request, int $terminalId)
    {
        $terminal = $this->terminal($request, $terminalId);
        $updated = DB::transaction(function () use ($request, $terminal) {
            $location = $this->locations($request, (int) $terminal->business_id)
                ->lockForUpdate()->findOrFail($terminal->location_id);
            $settings = EisSetting::where('business_id', $terminal->business_id)
                ->where('location_id', $location->id)->lockForUpdate()->findOrFail($terminal->id);
            $remoteActive = DB::table('eis_terminal_configurations as terminal')
                ->join('eis_configurations as configuration', 'configuration.id', '=', 'terminal.configuration_id')
                ->where('configuration.eis_setting_id', $settings->id)
                ->where('configuration.business_id', $settings->business_id)
                ->where('configuration.location_id', $location->id)
                ->value('terminal.is_active');
            if (!TerminalContext::isOperational((bool) $settings->status, $remoteActive)) {
                return false;
            }
            $location->default_eis_setting_id = $terminal->id;
            $location->save();

            return true;
        });

        if (!$updated) {
            return $this->result(['success' => false, 'message' => 'Activate this terminal before setting it as the location default.']);
        }

        return $this->result(['success' => true, 'message' => 'Default terminal updated for this location.']);
    }

    private function businessId(Request $request): int
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->can('business_settings.access'), 403);
        $businessId = (int) $request->user()->business_id;
        abort_unless($businessId > 0, 403);

        return $businessId;
    }

    private function locations(Request $request, int $businessId)
    {
        $query = BusinessLocation::where('business_id', $businessId);
        $permitted = $request->user()->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('id', $permitted);
        }

        return $query;
    }

    private function terminal(Request $request, int $terminalId): EisSetting
    {
        $businessId = $this->businessId($request);
        $terminal = EisSetting::where('business_id', $businessId)
            ->whereIn('location_id', $this->locations($request, $businessId)->select('id'))
            ->findOrFail($terminalId);

        return TerminalContext::settings($businessId, null, $terminalId, (int) $terminal->location_id);
    }

    private function result(array $result)
    {
        $success = (bool) ($result['success'] ?? false);
        $message = $result['message'] ?? $result['msg'] ?? ($success ? 'Terminal updated.' : 'Terminal action failed.');

        // Management uses session authentication; credentials stay on the server.
        return response()->json(['success' => $success, 'message' => $message, 'msg' => $message], $success ? 200 : 422);
    }
}
