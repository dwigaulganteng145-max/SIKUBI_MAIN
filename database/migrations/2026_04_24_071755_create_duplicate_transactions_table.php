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
        Schema::create('duplicate_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->dateTime('transaction_date');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['DEBIT', 'CREDIT']);
            $table->json('raw_data')->nullable();
            $table->string('deduplication_hash', 64);
            $table->enum('status', ['PENDING', 'IMPORTED', 'DISMISSED'])->default('PENDING');
            $table->timestamps();
            
            $table->index('status');
            $table->index('import_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_transactions');
    }
};
