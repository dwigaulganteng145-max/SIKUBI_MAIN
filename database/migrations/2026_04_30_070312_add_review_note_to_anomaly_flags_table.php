<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anomaly_flags', function (Blueprint $table) {
            $table->text('review_note')->nullable()->after('is_dismissed');
        });
    }

    public function down(): void
    {
        Schema::table('anomaly_flags', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};
