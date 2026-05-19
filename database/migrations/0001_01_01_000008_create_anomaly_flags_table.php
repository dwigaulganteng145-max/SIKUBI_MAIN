<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomaly_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->string('detection_method', 50);
            $table->decimal('score', 5, 4);
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH']);
            $table->text('reason');
            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            $table->index(['severity', 'is_reviewed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_flags');
    }
};
