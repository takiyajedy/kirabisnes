<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_items', function (Blueprint $table) {
            $table->id();
            // What this is: something you bill a client for, or something you
            // pay for yourself.
            $table->enum('kind', ['client_billing', 'own_cost']);

            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('amount')->default(0);

            $table->enum('interval', ['monthly', 'quarterly', 'yearly']);
            $table->date('next_date');

            /*
             * The day of the month the charge belongs on, kept separately so a
             * date that had to be clamped can come back. A monthly item
             * starting on the 31st runs 31 Jan, 28 Feb, 31 Mar — reading the
             * day back off next_date would leave it stuck on the 28th.
             */
            $table->unsignedTinyInteger('anchor_day');

            // client_billing: draft the invoice without being asked.
            $table->boolean('auto_generate')->default(false);
            $table->unsignedSmallInteger('remind_days_before')->default(14);

            /*
             * When the owner was last told about this item. An own cost is
             * only moved along when the owner marks it paid, so without this
             * the reminder would arrive every day of the warning window.
             */
            $table->timestamp('last_reminded_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'is_active']);
            $table->index('next_date');
        });

        // The columns were created with their tables in earlier phases.
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('recurring_item_id')
                ->references('id')->on('recurring_items')
                ->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('recurring_item_id')
                ->references('id')->on('recurring_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['recurring_item_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['recurring_item_id']);
        });

        Schema::dropIfExists('recurring_items');
    }
};
