<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('monthly_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['monthly_plan_id', 'category_id']);
            $table->index(['user_id', 'category_id']);
        });

        DB::statement('alter table monthly_plan_items add constraint monthly_plan_items_amount_non_negative check (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_plan_items');
    }
};
