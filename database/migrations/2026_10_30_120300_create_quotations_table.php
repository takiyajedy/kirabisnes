<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->string('number')->unique();
            $table->date('issue_date');
            $table->date('valid_until');

            $table->enum('status', [
                'draft', 'sent', 'accepted', 'rejected', 'expired',
            ])->default('draft');

            // The language the document is written in, taken from the client.
            $table->string('locale', 5);

            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('total')->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            // The invoice this quotation became. The foreign key is added
            // after the invoices table exists.
            $table->unsignedBigInteger('converted_invoice_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('issue_date');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->bigInteger('unit_price')->default(0);
            // quantity x unit_price, worked out once and stored, so a past
            // document never changes when the rounding rules are touched.
            $table->bigInteger('amount')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
