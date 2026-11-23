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
            $table->decimal('baseline_amount', 15, 2)->default(0)->after('target_amount');
            $table->decimal('frozen_amount', 15, 2)->nullable()->after('baseline_amount');
        });

        DB::statement('alter table goals add constraint goals_baseline_amount_non_negative check (baseline_amount >= 0)');
        DB::statement('alter table goals add constraint goals_frozen_amount_non_negative check (frozen_amount >= 0)');
        DB::statement('alter table goals add constraint goals_frozen_requires_archived check (frozen_amount is null or archived_at is not null)');

        DB::statement("
            update goals as g
            set frozen_amount = greatest(coalesce((
                select coalesce(sum(amount) filter (where reserve_action = 'deposit'), 0)
                    - coalesce(sum(amount) filter (where reserve_action = 'withdraw'), 0)
                from transactions as t
                where t.user_id = g.user_id
                    and t.category_id = g.category_id
                    and t.type = 'reserve'
                    and t.status = 'paid'
            ), 0), 0)
            where g.archived_at is not null
        ");
    }

    public function down(): void
    {
        DB::statement('alter table goals drop constraint if exists goals_baseline_amount_non_negative');
        DB::statement('alter table goals drop constraint if exists goals_frozen_amount_non_negative');
        DB::statement('alter table goals drop constraint if exists goals_frozen_requires_archived');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['baseline_amount', 'frozen_amount']);
        });
    }
};
