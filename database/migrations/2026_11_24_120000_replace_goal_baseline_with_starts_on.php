<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('target_amount');
        });

        // created_at is stored in America/Sao_Paulo, so the calendar date is the start date.
        DB::statement('update goals set starts_on = created_at::date where baseline_amount > 0');

        DB::statement('alter table goals drop constraint if exists goals_baseline_amount_non_negative');
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('baseline_amount');
        });
    }

    public function down(): void
    {
        // The previous baseline amounts are not restored. The column comes back as zero.
        Schema::table('goals', function (Blueprint $table) {
            $table->decimal('baseline_amount', 15, 2)->default(0)->after('target_amount');
        });

        DB::statement('alter table goals add constraint goals_baseline_amount_non_negative check (baseline_amount >= 0)');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('starts_on');
        });
    }
};
