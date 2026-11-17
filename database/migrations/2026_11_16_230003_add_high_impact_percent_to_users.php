<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('high_impact_percent')->default(30);
        });

        DB::statement('alter table users add constraint users_high_impact_percent_range check (high_impact_percent between 1 and 100)');
    }

    public function down(): void
    {
        DB::statement('alter table users drop constraint if exists users_high_impact_percent_range');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('high_impact_percent');
        });
    }
};
