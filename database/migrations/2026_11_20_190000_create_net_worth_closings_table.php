<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('net_worth_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'month']);
        });

        DB::statement('alter table net_worth_closings add constraint net_worth_closings_month_first_day check (extract(day from "month") = 1)');

        Schema::create('net_worth_closing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('closing_id')->constrained('net_worth_closings')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->decimal('balance', 15, 2);
            $table->timestamps();

            $table->unique(['closing_id', 'bank_id']);
            $table->index(['user_id', 'bank_id']);
        });

        DB::statement('alter table net_worth_closing_items add constraint net_worth_closing_items_balance_non_negative check (balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('net_worth_closing_items');
        Schema::dropIfExists('net_worth_closings');
    }
};
