<?php

namespace Modules\EIS\Services\Stock;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\EisSubscriptionAccess;

class StockOperationsClient
{
    public function lookup(EisSetting $setting, string $operation, array $query = []): array
    {
        $paths = [
            'warehouse-inventory' => ['GET', 'stock/warehouse-inventory'],
            'get-raw-material' => ['GET', 'raw-material/get-raw-material'],
            'get-suppliers' => ['POST', 'stock/get-suppliers'],
            'get-hs-codes' => ['GET', 'stock/get-hs-codes'],
            'get-units-of-measure' => ['GET', 'stock/get-units-of-measure'],
        ];
        if (!isset($paths[$operation])) { throw new \InvalidArgumentException('Select a documented stock lookup.'); }
        $query = StockToolsOperations::payload($setting, $operation, $query);
        [$method, $path] = $paths[$operation];
        $response = $this->request($setting)->send($method, $path, $query ? ['query' => $query] : []);
        $body = $response->json();
        if (!$response->successful() || !is_array($body) || ($body['statusCode'] ?? null) !== 1
            || !empty($body['errors']) || !is_array($body['data'] ?? null)) {
            throw new \UnexpectedValueException('EIS returned an unsuccessful or invalid stock lookup.');
        }
        $data = $body['data'];
        if (in_array($operation, ['warehouse-inventory', 'get-raw-material'], true)) {
            if (array_key_exists('stocks', $data) && $data['stocks'] === null && ($data['total'] ?? null) === 0) {
                $data['stocks'] = [];
                $body['data'] = $data;
            }
            if (!is_array($data['stocks'] ?? null) || !array_is_list($data['stocks'])
                || !is_int($data['total'] ?? null) || $data['total'] < 0
                || ($data['page'] ?? null) !== $query['page'] || ($data['pageSize'] ?? null) !== $query['pageSize']
                || count($data['stocks']) > $query['pageSize'] || count($data['stocks']) > $data['total']) {
                throw new \UnexpectedValueException('EIS returned invalid inventory pagination.');
            }
            foreach ($data['stocks'] as $stock) {
                if (!is_array($stock) || (isset($stock['barcode']) && !is_string($stock['barcode']))
                    || !is_numeric($stock['currentQuantity'] ?? null) || !is_finite((float) $stock['currentQuantity'])) {
                    throw new \UnexpectedValueException('EIS returned an invalid inventory item.');
                }
            }
        } elseif (!array_is_list($data)) {
            throw new \UnexpectedValueException('EIS returned an invalid lookup list.');
        } else {
            $identity = match ($operation) {
                'get-suppliers' => 'supplierId', 'get-hs-codes' => 'code', 'get-units-of-measure' => 'unitOfMeasure',
            };
            foreach ($data as $item) {
                $value = is_array($item) ? ($item[$identity] ?? null) : null;
                if ($operation === 'get-suppliers' ? (!is_int($value) || $value <= 0)
                    : (!is_string($value) || trim($value) === '')) {
                    throw new \UnexpectedValueException('EIS returned an invalid lookup item.');
                }
            }
        }
        return array_intersect_key($body, array_flip(['statusCode', 'remark', 'errors', 'data']));
    }

    public function submitTool(EisSetting $setting, string $operation, array $payload): array
    {
        $path = match ($operation) {
            'warehouse-transfer' => 'stock/transfer-inventory',
            'submit-conversion' => 'raw-material/submit-conversion',
            'add-product' => 'stock/add-product',
            default => throw new \InvalidArgumentException('Select a documented stock mutation.'),
        };
        $response = $this->request($setting)->post($path, $payload);
        $result = $this->result($response);
        $body = $response->json();
        if (is_array($body) && array_key_exists('data', $body)) { $result['response']['data'] = $body['data']; }
        return $result;
    }

    public function adjustmentReasons(EisSetting $setting): array
    {
        $response = $this->request($setting)
            ->send('POST', 'stock/getStockAdjustmentReasons');
        $data = $response->json();
        if (!$response->successful() || !is_array($data) || (int) ($data['statusCode'] ?? 0) !== 1
            || !empty($data['errors'])) {
            throw new \UnexpectedValueException('EIS could not provide stock adjustment reasons.');
        }
        $reasons = $data['data'] ?? null;
        if (!is_array($reasons)) {
            throw new \UnexpectedValueException('EIS returned an invalid stock adjustment reason list.');
        }

        $descriptions = [];
        foreach ($reasons as $reason) {
            if (is_array($reason) && is_string($reason['description'] ?? null)
                && trim($reason['description']) !== '') {
                $descriptions[] = trim($reason['description']);
            }
        }

        return array_values(array_unique($descriptions));
    }

    public function submitAdjustment(EisSetting $setting, array $payload): array
    {
        return $this->result($this->request($setting)->post('stock/submit-adjustment', $payload));
    }

    public function submitTransfer(EisSetting $setting, array $payload): array
    {
        return $this->result($this->request($setting)->post('stock/transfer-inventory', $payload));
    }

    public function submitInformalPurchase(EisSetting $setting, array $payload): array
    {
        return $this->result($this->request($setting)->post('stock/submit-informal-purchase', $payload));
    }

    private function request(EisSetting $setting)
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        $base = rtrim(trim((string) config('eis.base_url')), '/');
        if ($base === '' || !is_string($setting->jwt_token) || trim($setting->jwt_token) === '') {
            throw new \InvalidArgumentException('EIS stock operations require a configured endpoint and terminal token.');
        }
        if (!preg_match('~/api/v1$~', $base)) {
            $base .= '/api/v1';
        }
        TerminalContext::assertBusiness($setting, (int) $setting->business_id);
        TerminalContext::site($setting);

        return Http::baseUrl($base.'/')
            ->withToken($setting->jwt_token)
            ->acceptJson()
            ->asJson()
            ->withOptions(['allow_redirects' => false])
            ->timeout(30)
            ->connectTimeout(10);
    }

    private function result(Response $response): array
    {
        $body = $response->json();
        $safe = [];
        if (is_array($body)) {
            foreach (['statusCode', 'remark', 'errors'] as $key) {
                if (array_key_exists($key, $body)) {
                    $safe[$key] = $body[$key];
                }
            }
        }

        if ($response->serverError() || (!$response->successful() && !$response->clientError())
            || !is_array($body) || !array_key_exists('statusCode', $body)) {
            return ['outcome' => 'unknown', 'response' => $safe, 'message' => 'EIS did not return a conclusive stock operation result.'];
        }
        if ($response->clientError() || $body['statusCode'] === 0 || $body['statusCode'] === '0') {
            return ['outcome' => 'rejected', 'response' => $safe, 'message' => $this->remoteMessage($body)];
        }
        if (($body['statusCode'] !== 1 && $body['statusCode'] !== '1') || !empty($body['errors'])) {
            return [
                'outcome' => 'unknown',
                'response' => $safe,
                'message' => 'EIS returned mixed or incomplete stock operation results. Do not resubmit.',
            ];
        }

        return ['outcome' => 'submitted', 'response' => $safe, 'message' => null];
    }

    private function remoteMessage(array $body): string
    {
        $remark = $body['remark'] ?? null;
        if (is_string($remark) && trim($remark) !== '') {
            return mb_substr(trim($remark), 0, 1000);
        }

        return 'EIS rejected the stock operation.';
    }
}
