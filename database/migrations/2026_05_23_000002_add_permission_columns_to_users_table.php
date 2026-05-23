<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_import')->default(true);
            $table->boolean('can_manage_accounts')->default(true);
            $table->boolean('can_manage_settings')->default(true);
            $table->boolean('can_detect_anomalies')->default(true);
            $table->boolean('can_edit_transactions')->default(true);
            $table->boolean('can_manage_cash_transactions')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'can_import',
                'can_manage_accounts',
                'can_manage_settings',
                'can_detect_anomalies',
                'can_edit_transactions',
                'can_manage_cash_transactions',
            ]);
        });
    }
};
