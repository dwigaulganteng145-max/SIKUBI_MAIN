<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('anomaly_flags', function (Blueprint $table) {
            $table->boolean('ask_pimpinan_review')->default(false)->after('review_note');
            $table->string('pimpinan_review_status', 20)->default('PENDING')->after('ask_pimpinan_review'); // PENDING, ANOMALY, VALID
            $table->text('pimpinan_review_note')->nullable()->after('pimpinan_review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anomaly_flags', function (Blueprint $table) {
            $table->dropColumn(['ask_pimpinan_review', 'pimpinan_review_status', 'pimpinan_review_note']);
        });
    }
};
