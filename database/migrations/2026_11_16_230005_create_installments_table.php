<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->unsignedSmallInteger('installments_count');
            $table->date('first_date');
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'first_date']);
        });

        DB::statement('alter table installments add constraint installments_total_positive check (total_amount > 0)');
        DB::statement('alter table installments add constraint installments_count_range check (installments_count between 2 and 120)');
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
