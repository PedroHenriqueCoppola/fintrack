<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('reserve_action')->nullable()->after('type');
        });

        DB::table('transactions')
            ->where('type', 'reserve')
            ->update(['reserve_action' => 'deposit']);

        DB::statement("alter table transactions add constraint transactions_reserve_action_check check (
            (type = 'reserve' and reserve_action is not null and reserve_action in ('deposit', 'withdraw'))
            or (type <> 'reserve' and reserve_action is null)
        )");
    }

    public function down(): void
    {
        DB::statement('alter table transactions drop constraint if exists transactions_reserve_action_check');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('reserve_action');
        });
    }
};
