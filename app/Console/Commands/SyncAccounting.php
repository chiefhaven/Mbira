<?php

namespace App\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Services\AccrualPostingService;
use RuntimeException;

class SyncAccounting extends Command
{
    protected $signature = 'accounting:sync {business_id} {--dry-run}';

    protected $description = 'Backfill eligible accounting postings for one business.';

    public function handle(AccrualPostingService $postingService): int
    {
        $businessId = filter_var($this->argument('business_id'), FILTER_VALIDATE_INT);
        if ($businessId === false || $businessId < 1) {
            $this->error('Provide a valid business ID.');
            return self::INVALID;
        }

        try {
            $postingService->assertBusinessReady($businessId);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $query = Transaction::where('business_id', $businessId)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('type', 'sell')->where('status', 'final');
                })->orWhere(function ($query) {
                    $query->where('type', 'purchase')->where('status', 'received');
                })->orWhere(function ($query) {
                    $query->where('type', 'expense')->where('status', 'final');
                })->orWhere(function ($query) {
                    $this->whereEligiblePayroll($query);
                });
            });
        $total = (clone $query)->count();
        $paymentTotal = DB::table('transaction_payments as payments')
            ->join('transactions', 'transactions.id', '=', 'payments.transaction_id')
            ->where('transactions.business_id', $businessId)
            ->whereNull('payments.parent_id')
            ->where('payments.method', '!=', 'advance')
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('transactions.type', 'sell')->where('transactions.status', 'final');
                })->orWhere(function ($query) {
                    $query->where('transactions.type', 'purchase')->where('transactions.status', 'received');
                })->orWhere(function ($query) {
                    $query->where('transactions.type', 'expense')->where('transactions.status', 'final');
                })->orWhere(function ($query) {
                    $this->whereEligiblePayroll($query);
                });
            })
            ->count();

        if ($this->option('dry-run')) {
            $counts = (clone $query)
                ->select('type')
                ->selectRaw('COUNT(*) as row_count')
                ->groupBy('type')
                ->orderBy('type')
                ->get();

            foreach ($counts as $count) {
                $this->line($count->type . ': ' . $count->row_count);
            }
            $this->info("{$total} eligible transactions and {$paymentTotal} eligible payments; no postings written.");
            return self::SUCCESS;
        }

        $this->output->progressStart($total);
        $processed = 0;
        $query->orderBy('id')->chunkById(100, function ($transactions) use ($postingService, &$processed) {
            foreach ($transactions as $transaction) {
                $postingService->syncTransaction($transaction);
                ++$processed;
                $this->output->progressAdvance();
            }
        });
        $this->output->progressFinish();

        $this->info("Processed {$processed} eligible transactions for business {$businessId}.");
        return self::SUCCESS;
    }

    private function whereEligiblePayroll($query): void
    {
        $query->where('transactions.type', 'payroll')
            ->where('transactions.status', 'final')
            ->where(function ($query) {
                $query->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('essentials_payroll_group_transactions as payroll_links')
                        ->whereColumn('payroll_links.transaction_id', 'transactions.id');
                })->orWhereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('essentials_payroll_group_transactions as payroll_links')
                        ->join('essentials_payroll_groups as payroll_groups', 'payroll_groups.id', '=', 'payroll_links.payroll_group_id')
                        ->whereColumn('payroll_links.transaction_id', 'transactions.id')
                        ->whereColumn('payroll_groups.business_id', 'transactions.business_id')
                        ->where('payroll_groups.status', 'final');
                });
            });
    }
}