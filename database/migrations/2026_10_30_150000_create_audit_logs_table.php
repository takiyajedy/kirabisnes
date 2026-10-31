<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Which record, without a foreign key: the log has to outlive the
            // row it describes, including a hard delete.
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            $table->string('event', 20);
            // A label worth reading in a list: an invoice number, a
            // transaction description. Kept because the row it names may be
            // gone by the time anyone looks.
            $table->string('subject')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();

            // Only the fields that moved, each with its before and after.
            $table->json('changes')->nullable();

            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
