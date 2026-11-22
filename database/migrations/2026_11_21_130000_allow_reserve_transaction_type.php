<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table transactions drop constraint transactions_type_check');
        DB::statement("alter table transactions add constraint transactions_type_check check (((type)::text = ANY ((ARRAY['income'::character varying, 'expense'::character varying, 'reserve'::character varying])::text[])))");
    }

    public function down(): void
    {
        DB::statement('alter table transactions drop constraint transactions_type_check');
        DB::statement("alter table transactions add constraint transactions_type_check check (((type)::text = ANY ((ARRAY['income'::character varying, 'expense'::character varying])::text[])))");
    }
};
