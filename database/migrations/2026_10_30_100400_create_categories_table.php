<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->enum('type', ['income', 'expense']);
            $table->foreignId('default_fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->boolean('tax_deductible_default')->default(false);
            $table->string('color', 20)->default('#71717a');
            $table->string('icon')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
