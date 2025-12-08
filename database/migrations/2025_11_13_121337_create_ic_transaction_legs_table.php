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
        Schema::create('ic_transaction_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ic_transaction_id')
                ->constrained('ic_transactions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('counterparty_company_id')
                ->constrained('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('leg_role_id')
                ->constrained('leg_roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('leg_nature_id')
                ->constrained('leg_natures')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('hfm_account_id')
                ->constrained('hfm_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('status_id')
                ->constrained('leg_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('prepared_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('reviewed_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->foreignId('agreement_status_id')
                ->nullable()
                ->constrained('agreement_statuses')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('disagree_reason')->nullable();
            $table->decimal('counterparty_amount_snapshot', 18, 2)->nullable();
            $table->timestamps();
            $table->index(['ic_transaction_id', 'leg_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ic_transaction_legs');
    }
};
