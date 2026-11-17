<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('fixed_expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->date('generated_for')->nullable();

            $table->index(['user_id', 'generated_for']);
        });

        DB::statement('create unique index transactions_fixed_month_unique on transactions (fixed_expense_id, generated_for) where fixed_expense_id is not null');
        DB::statement('create unique index transactions_installment_number_unique on transactions (installment_id, installment_number) where installment_id is not null');
        DB::statement("alter table transactions add constraint transactions_single_source_check check (
            (
                fixed_expense_id is null
                and installment_id is null
                and installment_number is null
                and generated_for is null
            ) or (
                fixed_expense_id is not null
                and installment_id is null
                and installment_number is null
                and generated_for is not null
                and extract(day from generated_for) = 1
            ) or (
                fixed_expense_id is null
                and installment_id is not null
                and installment_number is not null
                and generated_for is not null
                and extract(day from generated_for) = 1
            )
        )");
    }

    public function down(): void
    {
        DB::statement('alter table transactions drop constraint if exists transactions_single_source_check');
        DB::statement('drop index if exists transactions_fixed_month_unique');
        DB::statement('drop index if exists transactions_installment_number_unique');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'generated_for']);
            $table->dropConstrainedForeignId('fixed_expense_id');
            $table->dropConstrainedForeignId('installment_id');
            $table->dropColumn(['installment_number', 'generated_for']);
        });
    }
};
