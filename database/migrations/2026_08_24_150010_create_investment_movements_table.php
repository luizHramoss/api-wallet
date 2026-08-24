<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['buy', 'sell', 'dividend']);
            // Nulos em dividendos (não têm quantidade/preço por cota, só o
            // valor em caixa recebido).
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('price', 15, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('occurred_at');
            $table->timestamps();

            $table->index(['investment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_movements');
    }
};
