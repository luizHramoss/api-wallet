<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->enum('status', ['planned', 'realized'])->default('realized');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->date('occurred_at');
            $table->timestamps();

            $table->index(['account_id', 'type']);
            $table->index(['account_id', 'occurred_at']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
