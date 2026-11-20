<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->date('closing_month');
            $table->date('paid_on');
            $table->timestamps();

            $table->unique(['card_id', 'closing_month']);
        });

        DB::statement('alter table card_invoice_payments add constraint card_invoice_payments_closing_month_check check (extract(day from closing_month) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('card_invoice_payments');
    }
};
