<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->dateTime('transaction_date');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['DEBIT', 'CREDIT']);
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('classification_method', 30)->default('UNCLASSIFIED');
            $table->decimal('confidence_score', 3, 2)->default(0);
            $table->json('raw_data')->nullable();
            $table->string('deduplication_hash', 64)->unique();
            $table->timestamps();

            $table->index(['bank_account_id', 'transaction_date']);
            $table->index(['type', 'category_id']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
