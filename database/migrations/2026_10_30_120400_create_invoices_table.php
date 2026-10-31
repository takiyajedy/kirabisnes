<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            // Filled in from phase 4; that table does not exist yet.
            $table->unsignedBigInteger('recurring_item_id')->nullable();

            $table->string('number')->unique();
            $table->date('issue_date');
            $table->date('due_date');

            $table->enum('status', [
                'draft', 'sent', 'partial', 'paid', 'overdue', 'void',
            ])->default('draft');

            $table->string('locale', 5);

            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('total')->default(0);
            // The sum of the payments recorded against this invoice.
            $table->bigInteger('paid_amount')->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('due_date');
            $table->index('issue_date');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('amount')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('converted_invoice_id')
                ->references('id')->on('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['converted_invoice_id']);
        });

        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
