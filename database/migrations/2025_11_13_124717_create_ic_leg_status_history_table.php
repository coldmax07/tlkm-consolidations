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
        Schema::create('ic_leg_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ic_transaction_leg_id')
                ->constrained('ic_transaction_legs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('from_status_id')
                ->nullable()
                ->constrained('leg_statuses')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('to_status_id')
                ->constrained('leg_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('changed_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ic_leg_status_history');
    }
};
