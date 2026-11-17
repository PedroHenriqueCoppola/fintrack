<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->decimal('expected_income', 15, 2)->default(0);
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'month']);
            $table->index(['user_id', 'status']);
        });

        DB::statement('alter table monthly_plans add constraint monthly_plans_income_non_negative check (expected_income >= 0)');
        DB::statement('alter table monthly_plans add constraint monthly_plans_month_first_day check (extract(day from "month") = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_plans');
    }
};
