<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('applies_to', ['expense', 'income']);
            $table->enum('nature', ['spending', 'reserve'])->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->foreignId('pocket_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->date('initial_month')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table categories add constraint categories_nature_applies_to_check check (
            (applies_to = 'income' and nature is null)
            or (applies_to = 'expense' and nature is not null)
        )");

        DB::statement('alter table categories add constraint categories_initial_balance_non_negative check (initial_balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
