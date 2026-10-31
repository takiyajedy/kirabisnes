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
            $table->enum('type', [
                'income', 'expense', 'transfer', 'owner_draw', 'capital_injection',
            ]);

            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            // Destination account, for transfers between own accounts.
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->restrictOnDelete();

            // Always positive; the type decides the direction.
            $table->bigInteger('amount');

            $table->date('date');
            $table->string('description');
            $table->string('reference')->nullable();

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            // The fund an expense or owner draw is taken from.
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();

            // Filled in from Phase 3 and 4 onwards; the tables do not exist yet,
            // so the foreign keys are added with those migrations.
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('recurring_item_id')->nullable();

            $table->boolean('tax_deductible')->default(false);
            $table->string('receipt_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
