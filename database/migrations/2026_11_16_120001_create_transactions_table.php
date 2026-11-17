<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->enum('status', ['paid', 'pending']);
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->date('competence_date')->nullable();
            $table->string('description', 120);
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'bank_id']);
            $table->index(['user_id', 'status']);
        });

        DB::statement('alter table transactions add constraint transactions_amount_positive check (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
