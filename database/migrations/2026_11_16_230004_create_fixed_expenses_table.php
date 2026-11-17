<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('due_day');
            $table->date('start_month');
            $table->date('end_month')->nullable();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'start_month']);
        });

        DB::statement('alter table fixed_expenses add constraint fixed_expenses_amount_positive check (amount > 0)');
        DB::statement('alter table fixed_expenses add constraint fixed_expenses_due_day_range check (due_day between 1 and 31)');
        DB::statement('alter table fixed_expenses add constraint fixed_expenses_start_month_first_day check (extract(day from start_month) = 1)');
        DB::statement('alter table fixed_expenses add constraint fixed_expenses_end_month_first_day check (end_month is null or extract(day from end_month) = 1)');
        DB::statement('alter table fixed_expenses add constraint fixed_expenses_end_after_start check (end_month is null or end_month >= start_month)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_expenses');
    }
};
