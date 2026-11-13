<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('closing_day');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->string('color')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        DB::statement('alter table cards add constraint cards_closing_day_check check (closing_day between 1 and 31)');
        DB::statement('alter table cards add constraint cards_due_day_check check (due_day is null or due_day between 1 and 31)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
