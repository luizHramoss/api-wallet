<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_bill_id')->nullable()->after('category_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('transfer_pair_id')->nullable()->after('recurring_bill_id')
                ->constrained('transactions')->nullOnDelete();
            $table->enum('transfer_direction', ['in', 'out'])->nullable()->after('transfer_pair_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_pair_id');
            $table->dropConstrainedForeignId('recurring_bill_id');
            $table->dropColumn('transfer_direction');
        });
    }
};
