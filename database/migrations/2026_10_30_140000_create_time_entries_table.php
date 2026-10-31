<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->timestamp('started_at');
            // Null while the timer is still running.
            $table->timestamp('ended_at')->nullable();
            /*
             * Whole minutes, worked out when the timer stops or typed in by
             * hand. Stored rather than derived so a rounding rule can change
             * without rewriting what was already logged — the same reason
             * invoice line amounts are stored.
             */
            $table->unsignedInteger('minutes')->nullable();

            $table->string('note')->nullable();
            $table->boolean('billable')->default(true);

            $table->timestamps();

            $table->index(['project_id', 'started_at']);
            // Finding the one running timer has to be cheap: it is looked up
            // on every page, for the top bar widget.
            $table->index('ended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
