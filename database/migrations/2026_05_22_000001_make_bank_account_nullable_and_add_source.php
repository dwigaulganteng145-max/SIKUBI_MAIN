<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add source column if it doesn't exist yet
        if (!Schema::hasColumn('transactions', 'source')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('source', 20)->default('IMPORT')->after('deduplication_hash');
            });
        }

        // Make bank_account_id nullable for cash transactions (SQLite rebuild)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            // Rename current table
            DB::statement('ALTER TABLE transactions RENAME TO transactions_old');

            // Create new table with bank_account_id nullable
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('import_batch_id')->nullable();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->dateTime('transaction_date');
                $table->text('description');
                $table->decimal('amount', 15, 2);
                $table->string('type', 10);
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('classification_method', 30)->default('UNCLASSIFIED');
                $table->decimal('confidence_score', 3, 2)->default(0);
                $table->json('raw_data')->nullable();
                $table->string('deduplication_hash', 64)->unique();
                $table->string('source', 20)->default('IMPORT');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('import_batch_id')->references('id')->on('import_batches')->onDelete('set null');
                $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

                $table->index(['bank_account_id', 'transaction_date']);
                $table->index(['type', 'category_id']);
                $table->index('transaction_date');
                $table->index('source');
            });

            // Copy all existing data
            DB::statement('INSERT INTO transactions (id, import_batch_id, bank_account_id, transaction_date, description, amount, type, category_id, classification_method, confidence_score, raw_data, deduplication_hash, source, created_at, updated_at, deleted_at) SELECT id, import_batch_id, bank_account_id, transaction_date, description, amount, type, category_id, classification_method, confidence_score, raw_data, deduplication_hash, source, created_at, updated_at, deleted_at FROM transactions_old');

            DB::statement('DROP TABLE transactions_old');

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->change();
                $table->dropForeign(['bank_account_id']);
                $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            });
        }

        // Add source index if missing
        try {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('source');
            });
        } catch (\Exception $e) {
            // Index already exists, ignore
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'source')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
