<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->enum('type', ['stock', 'fixed_income', 'fund', 'crypto', 'other']);
            // quantity/average_price são derivados dos movimentos (ver
            // investment_movements) - nunca editados diretamente por fora do
            // InvestmentService, são um cache calculado pra não recalcular a
            // partir do ledger inteiro a cada leitura.
            $table->decimal('quantity', 20, 8)->default(0);
            $table->decimal('average_price', 15, 4)->default(0);
            // Preço atual: atualizado manualmente por enquanto (sem
            // integração com corretora/cotação automática ainda).
            $table->decimal('current_price', 15, 4)->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
