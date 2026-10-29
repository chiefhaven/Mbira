<?php

namespace Modules\EIS\Services\Sales;

use App\Transaction;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Exceptions\EisSaleException;
use Modules\EIS\Models\EisSale;
use Modules\EIS\Services\Terminal\TerminalContext;

class DirectSaleSubmissionService
{
    public function __construct(private SaleSubmissionService $submission) {}

    public function submit(Transaction $transaction): array
    {
        if (!SaleSubmissionRule::qualifies($transaction)) {
            return ['status' => 'skipped', 'message' => 'Sale is not final and fully paid.'];
        }

        try {
            $transaction->refresh();
            if (!SaleSubmissionRule::qualifies($transaction)) {
                return ['status' => 'skipped', 'message' => 'Sale is not final and fully paid.'];
            }

            $settings = TerminalContext::forSale($transaction);
            $sale = $this->submission->submit($transaction, $settings, true);

            return [
                'status' => $sale->status,
                'message' => $sale->error_message,
                'eis_sale_id' => $sale->id,
            ];
        } catch (EisSaleException $exception) {
            Log::error('Direct EIS sale submission failed', [
                'transaction_id' => $transaction->id,
                'business_id' => $transaction->business_id,
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]);
            $sale = EisSale::where('business_id', $transaction->business_id)
                ->where('transaction_id', $transaction->id)->first();

            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'eis_sale_id' => $sale?->id,
            ];
        } catch (\Throwable $exception) {
            Log::error('Direct EIS sale submission could not be completed', [
                'transaction_id' => $transaction->id,
                'business_id' => $transaction->business_id,
                'error' => $exception->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => $exception->getMessage(), 'eis_sale_id' => null];
        }
    }
}
