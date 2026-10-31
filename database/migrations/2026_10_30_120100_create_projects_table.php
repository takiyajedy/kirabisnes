<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            $table->bigInteger('contract_amount')->default(0);
            $table->enum('status', [
                'prospect', 'active', 'completed', 'on_hold', 'cancelled',
            ])->default('prospect');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // What an hour of your own time costs, for the profit report.
            $table->bigInteger('internal_hourly_rate')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
