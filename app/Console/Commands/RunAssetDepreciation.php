<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Services\AccrualPostingService;
use RuntimeException;

class RunAssetDepreciation extends Command
{
    protected $signature = 'accounting:assets-depreciation {business_id} {period : Month in YYYY-MM format} {--dry-run}';

    protected $description = 'Review or post monthly depreciation for linked assets.';

    public function handle(AccrualPostingService $postingService): int
    {
        $businessId = filter_var($this->argument('business_id'), FILTER_VALIDATE_INT);
        $period = $this->argument('period');
        if ($businessId === false || $businessId < 1) {
            $this->error('Provide a valid business ID.');
            return self::INVALID;
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Provide the period as YYYY-MM.');
            return self::INVALID;
        }

        try {
            $postingService->assertAssetPostingReady($businessId);
            $candidates = $postingService->assetDepreciationCandidates($businessId, $period . '-01');
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $total = 0.0;
        foreach ($candidates as $candidate) {
            $asset = $candidate['asset'];
            $amount = (float) $candidate['amount'];
            $total += $amount;
            $this->line(sprintf('%s | %s | %s', $asset->asset_code, $asset->name, number_format($amount, 4, '.', '')));
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Review only: %d assets, total monthly depreciation %s for %s; no postings written.',
                count($candidates),
                number_format($total, 4, '.', ''),
                $period
            ));
            return self::SUCCESS;
        }

        $posted = 0;
        foreach ($candidates as $candidate) {
            if ($postingService->postAssetDepreciation($candidate)) {
                ++$posted;
            }
        }

        $this->info(sprintf(
            'Posted depreciation for %d assets, total %s for %s.',
            $posted,
            number_format($total, 4, '.', ''),
            $period
        ));

        return self::SUCCESS;
    }
}
