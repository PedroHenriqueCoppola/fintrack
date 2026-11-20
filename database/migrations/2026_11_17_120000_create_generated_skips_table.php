<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('fixed_expense_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->date('generated_for');
            $table->timestamps();
        });

        DB::statement('create unique index generated_skips_fixed_month_unique on generated_skips (fixed_expense_id, generated_for) where fixed_expense_id is not null');
        DB::statement('create unique index generated_skips_installment_number_unique on generated_skips (installment_id, installment_number) where installment_id is not null');
        DB::statement('alter table generated_skips add constraint generated_skips_single_source_check check (
            (
                fixed_expense_id is not null
                and installment_id is null
                and installment_number is null
                and extract(day from generated_for) = 1
            ) or (
                fixed_expense_id is null
                and installment_id is not null
                and installment_number is not null
                and extract(day from generated_for) = 1
            )
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_skips');
    }
};
