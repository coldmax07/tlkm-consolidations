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
        Schema::create('ic_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')
                ->constrained('periods')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('transaction_template_id')
                ->constrained('transaction_templates')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('financial_statement_id')
                ->constrained('financial_statements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('sender_company_id')
                ->constrained('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('receiver_company_id')
                ->constrained('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('currency', 3);
            $table->boolean('created_from_default_amount')->default(false);
            $table->timestamps();
            $table->unique(['period_id', 'transaction_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ic_transactions');
    }
};
