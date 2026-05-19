<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('pattern');
            $table->enum('match_type', ['EXACT', 'CONTAINS', 'REGEX'])->default('CONTAINS');
            $table->integer('priority')->default(100);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'match_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_rules');
    }
};
