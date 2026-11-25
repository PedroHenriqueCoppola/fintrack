<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 30);
            $table->string('name_key', 30);
            $table->timestamps();

            $table->unique(['user_id', 'name_key']);
        });

        Schema::create('tag_transaction', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_transaction');
        Schema::dropIfExists('tags');
    }
};
