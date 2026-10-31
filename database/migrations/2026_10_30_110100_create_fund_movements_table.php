<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->cascadeOnDelete();

            $table->enum('type', [
                'allocate', 'spend', 'adjust', 'transfer_in', 'transfer_out',
            ]);

            // Signed: positive moves money into the fund, negative takes it out.
            $table->bigInteger('amount');

            $table->string('note')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index(['fund_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_movements');
    }
};
