<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['business', 'business_locations', 'eis_settings', 'eis_configurations',
                'eis_terminal_configurations', 'eis_terminal_sites', 'eis_offline_limits',
                'eis_sales', 'eis_failed_transactions', 'tax_rates'] as $name) {
                DB::statement('ALTER TABLE `'.$name.'` ENGINE=InnoDB');
            }
        }
        foreach (['eis_settings', 'eis_configurations', 'eis_sales', 'eis_failed_transactions'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->unsignedBigInteger('location_id')->nullable()->index();
                if ($name !== 'eis_settings') {
                    $table->unsignedBigInteger('eis_setting_id')->nullable()->index();
                }
            });
        }
        Schema::table('business_locations', function (Blueprint $table) {
            $table->unsignedBigInteger('default_eis_setting_id')->nullable()->index();
        });
        Schema::table('eis_sales', function (Blueprint $table) {
            $table->string('eis_invoice_number')->nullable()->index();
        });
        Schema::create('eis_migration_issues', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('business_id');
            $table->text('reason');
            $table->timestamps();
        });
        $report = function (string $table, object $row, string $reason): void {
            DB::table('eis_migration_issues')->insert([
                'source_table' => $table, 'source_id' => $row->id,
                'business_id' => $row->business_id, 'reason' => $reason,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        DB::table('eis_settings')->orderBy('id')->each(function ($setting) use ($report) {
            if (!$setting->device_id || DB::table('eis_settings')->where('device_id', $setting->device_id)->count() !== 1) {
                $report('eis_settings', $setting, 'Device identity must be present and uniquely owned before location assignment.');
                return;
            }
            if ($setting->branch_id === null || trim((string) $setting->branch_id) === '') {
                $report('eis_settings', $setting, 'Terminal site is missing; select an existing location explicitly.');
                return;
            }
            $locations = DB::table('business_locations')->where('business_id', $setting->business_id)
                ->where('eis_site_id', $setting->branch_id)->get();
            if ($locations->count() !== 1) {
                $report('eis_settings', $setting, 'Site must map to exactly one existing business location.');
                return;
            }
            DB::table('eis_settings')->where('id', $setting->id)->update(['location_id' => $locations->first()->id]);
        });
        DB::table('eis_configurations')->orderBy('id')->each(function ($configuration) use ($report) {
            $terminals = DB::table('eis_terminal_configurations')->where('configuration_id', $configuration->id)->get();
            if ($terminals->count() !== 1 || !$terminals->first()->terminal_id) {
                $report('eis_configurations', $configuration, 'Configuration must identify exactly one terminal.');
                return;
            }
            $settings = DB::table('eis_settings')->where('business_id', $configuration->business_id);
            if ($terminals->count() === 1 && $terminals->first()->terminal_id) {
                $settings->where('device_id', $terminals->first()->terminal_id);
            }
            $settings = $settings->get();
            if ($settings->count() !== 1 || !$settings->first()->location_id
                || DB::table('eis_configurations')->where('eis_setting_id', $settings->first()->id)->exists()) {
                $report('eis_configurations', $configuration, 'Configuration terminal ownership is ambiguous or unmapped.');
                return;
            }
            DB::table('eis_configurations')->where('id', $configuration->id)->update([
                'eis_setting_id' => $settings->first()->id, 'location_id' => $settings->first()->location_id,
            ]);
        });
        DB::table('business_locations')->orderBy('id')->each(function ($location) use ($report) {
            $settings = DB::table('eis_settings')->where('business_id', $location->business_id)
                ->where('location_id', $location->id)->get();
            if ($settings->count() === 1) {
                DB::table('business_locations')->where('id', $location->id)
                    ->update(['default_eis_setting_id' => $settings->first()->id]);
            } elseif ($settings->count() > 1) {
                $report('business_locations', $location, 'Multiple terminals are mapped; explicitly select the default for new sales.');
            }
        });
        DB::table('eis_sales')->orderBy('id')->each(function ($sale) use ($report) {
            $transaction = DB::table('transactions')->where('business_id', $sale->business_id)
                ->where('id', $sale->transaction_id)->first();
            $locationId = $transaction->location_id ?? null;
            $payload = json_decode($sale->request_payload ?? '{}', true);
            $number = $payload['invoiceHeader']['invoiceNumber'] ?? $sale->fiscal_invoice_number;
            $parts = explode('-', $number ?? '');
            $candidates = DB::table('eis_settings')->where('business_id', $sale->business_id)
                ->when($locationId, fn ($query) => $query->where('location_id', $locationId))->get();
            $hasInvoiceIdentity = is_string($number) && preg_match('~^[A-Za-z0-9+/]+(?:-[A-Za-z0-9+/]+){3}$~D', $number);
            if ($hasInvoiceIdentity) {
                $generator = new \Modules\EIS\Services\Sales\InvoiceNumberGenerator();
                $candidates = $candidates->filter(function ($setting) use ($parts, $generator) {
                    $terminals = DB::table('eis_terminal_configurations as terminal')
                        ->join('eis_configurations as configuration', 'configuration.id', '=', 'terminal.configuration_id')
                        ->where('configuration.eis_setting_id', $setting->id)->get();
                    return $terminals->contains(fn ($terminal) =>
                        $generator->base10ToBase64($terminal->taxpayer_id) === $parts[0]
                        && $generator->base10ToBase64($terminal->terminal_position) === $parts[1]);
                });
            } else {
                $candidates = collect();
            }
            $updates = ['location_id' => $locationId];
            if ($candidates->count() === 1 && $locationId) {
                $updates['eis_setting_id'] = $candidates->first()->id;
            } else {
                $report('eis_sales', $sale, 'Historical sale terminal cannot be assigned unambiguously; manual reconciliation required.');
            }
            DB::table('eis_sales')->where('id', $sale->id)->update($updates + ['eis_invoice_number' => $hasInvoiceIdentity ? $number : null]);
            DB::table('eis_failed_transactions')->where('business_id', $sale->business_id)
                ->where('transaction_id', $sale->transaction_id)->update($updates);
        });
        DB::table('eis_failed_transactions')->whereNull('eis_setting_id')->orderBy('id')
            ->each(function ($failed) use ($report) {
                $report('eis_failed_transactions', $failed, 'Retry record requires terminal reconciliation.');
            });
        Schema::table('eis_configurations', function (Blueprint $table) {
            $table->unique('eis_setting_id');
        });
    }

    public function down(): void
    {
        if (DB::table('eis_settings')->whereNotNull('location_id')->groupBy('business_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back multi-terminal EIS data to singleton business ownership.');
        }
        Schema::table('eis_configurations', fn (Blueprint $table) => $table->dropUnique(['eis_setting_id']));
        foreach (['eis_settings', 'eis_configurations', 'eis_sales', 'eis_failed_transactions'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropColumn('location_id');
                if ($name !== 'eis_settings') {
                    $table->dropColumn('eis_setting_id');
                }
            });
        }
        Schema::table('business_locations', fn (Blueprint $table) => $table->dropColumn('default_eis_setting_id'));
        Schema::table('eis_sales', fn (Blueprint $table) => $table->dropColumn('eis_invoice_number'));
        Schema::dropIfExists('eis_migration_issues');
    }
};
