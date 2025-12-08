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
        Schema::create('transaction_templates', function (Blueprint $table) {
            $table->id();
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
            $table->foreignId('sender_account_category_id')
                ->constrained('account_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('sender_hfm_account_id')
                ->constrained('hfm_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('receiver_account_category_id')
                ->constrained('account_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('receiver_hfm_account_id')
                ->constrained('hfm_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('currency', 3);
            $table->decimal('default_amount', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['financial_statement_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_templates');
    }
};
