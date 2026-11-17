<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table categories drop constraint if exists categories_initial_balance_non_negative');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('initial_balance');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('initial_balance', 15, 2)->default(0);
        });

        DB::statement('alter table categories add constraint categories_initial_balance_non_negative check (initial_balance >= 0)');
    }
};
