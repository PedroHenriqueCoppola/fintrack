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
            $table->timestamp('frozen_at')->nullable()->after('frozen_amount');
        });

        DB::statement('update goals set frozen_at = archived_at where frozen_amount is not null');

        DB::statement('alter table goals drop constraint if exists goals_frozen_requires_archived');
        DB::statement('alter table goals add constraint goals_frozen_pair check (
            (frozen_amount is null and frozen_at is null)
            or (frozen_amount is not null and frozen_at is not null)
        )');

        DB::statement('drop index if exists goals_one_active_category_unique');
        DB::statement('create unique index goals_one_in_course_category_unique on goals (user_id, category_id) where archived_at is null and frozen_amount is null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists goals_one_in_course_category_unique');
        DB::statement('create unique index goals_one_active_category_unique on goals (user_id, category_id) where archived_at is null');

        DB::statement('alter table goals drop constraint if exists goals_frozen_pair');
        DB::statement('alter table goals add constraint goals_frozen_requires_archived check (frozen_amount is null or archived_at is not null)');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('frozen_at');
        });
    }
};
