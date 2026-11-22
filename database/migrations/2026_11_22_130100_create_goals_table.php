<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->decimal('target_amount', 15, 2);
            $table->date('deadline')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        DB::statement('alter table goals add constraint goals_target_amount_positive check (target_amount > 0)');
        DB::statement('alter table goals add constraint goals_deadline_first_day check (deadline is null or extract(day from deadline) = 1)');
        DB::statement('create unique index goals_one_active_category_unique on goals (user_id, category_id) where archived_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
