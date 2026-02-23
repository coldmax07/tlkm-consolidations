<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ic_transaction_legs', function (Blueprint $table) {
            $table->decimal('adjustment_amount', 18, 2)
                ->default(0)
                ->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ic_transaction_legs', function (Blueprint $table) {
            $table->dropColumn('adjustment_amount');
        });
    }
};
