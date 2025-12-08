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
        Schema::create('hfm_account_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_statement_id')
                ->constrained('financial_statements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('sender_hfm_account_id')
                ->constrained('hfm_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('receiver_hfm_account_id')
                ->constrained('hfm_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['financial_statement_id', 'sender_hfm_account_id'], 'hfm_account_pairs_sender_unique');
            $table->unique(['financial_statement_id', 'receiver_hfm_account_id'], 'hfm_account_pairs_receiver_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hfm_account_pairs');
    }
};
