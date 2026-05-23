<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            // Drop any index conflict first
            DB::statement('DROP INDEX IF EXISTS anomaly_flags_severity_is_reviewed_index');

            // Rename key tables resiliently
            try {
                DB::statement('ALTER TABLE anomaly_flags RENAME TO anomaly_flags_old');
            } catch (\Exception $e) {
                // table might have already been renamed, or doesn't exist
            }

            // Clean up the index on the old renamed table if it exists
            DB::statement('DROP INDEX IF EXISTS anomaly_flags_severity_is_reviewed_index');

            // Create new table pointing to transactions table
            Schema::dropIfExists('anomaly_flags');
            
            Schema::create('anomaly_flags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id');
                $table->string('detection_method', 50);
                $table->decimal('score', 5, 4);
                $table->string('severity', 10);
                $table->text('reason');
                $table->boolean('is_reviewed')->default(false);
                $table->boolean('is_dismissed')->default(false);
                $table->timestamp('detected_at')->useCurrent();
                $table->timestamps();
                $table->text('review_note')->nullable();

                $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
                
                $table->index(['severity', 'is_reviewed']);
            });

            // Copy data resiliently
            try {
                DB::statement('INSERT INTO anomaly_flags (id, transaction_id, detection_method, score, severity, reason, is_reviewed, is_dismissed, detected_at, created_at, updated_at, review_note) SELECT id, transaction_id, detection_method, score, severity, reason, is_reviewed, is_dismissed, detected_at, created_at, updated_at, review_note FROM anomaly_flags_old');
                DB::statement('DROP TABLE anomaly_flags_old');
            } catch (\Exception $e) {
                // Table might not exist or copy already happened
            }

            DB::statement('PRAGMA foreign_keys=on');
        }
    }

    public function down(): void
    {
        // No rollback needed as schema layout is identical
    }
};
