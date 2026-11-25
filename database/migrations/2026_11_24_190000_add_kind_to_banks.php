<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('kind', 20)->default('account');
        });

        DB::statement("alter table banks add constraint banks_kind_check check (kind in ('account', 'investment'))");
    }

    public function down(): void
    {
        DB::statement('alter table banks drop constraint if exists banks_kind_check');

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
