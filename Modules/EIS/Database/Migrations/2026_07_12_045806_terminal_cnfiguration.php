<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {

            // Activation metadata
            $table->string('activated_by')->nullable()->after('activated_at');

            $table->timestamp('deactivated_at')->nullable()->after('activated_by');
            $table->string('deactivated_by')->nullable()->after('deactivated_at');
            $table->text('deactivation_reason')->nullable()->after('deactivated_by');

            $table->timestamp('toggled_at')->nullable()->after('deactivation_reason');
            $table->string('toggled_by')->nullable()->after('toggled_at');

            $table->string('activation_code')->nullable()->after('toggled_by');
            $table->string('activation_environment')->nullable()->after('activation_code');

            // Terminal details
            $table->string('terminal_id')->nullable()->after('activation_environment');
            $table->integer('terminal_position')->nullable()->after('terminal_id');
            $table->string('taxpayer_id')->nullable()->after('terminal_position');

            $table->timestamp('activation_date')->nullable()->after('taxpayer_id');

            // Authentication
            $table->longText('jwt_token')->nullable()->after('activation_date');
            $table->longText('secret_key')->nullable()->after('jwt_token');
        });
    }

    public function down(): void
    {
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {

            $table->dropColumn([
                'activated_by',
                'deactivated_at',
                'deactivated_by',
                'deactivation_reason',
                'toggled_at',
                'toggled_by',
                'activation_code',
                'activation_environment',
                'terminal_id',
                'terminal_position',
                'taxpayer_id',
                'activation_date',
                'jwt_token',
                'secret_key',
            ]);
        });
    }
};