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
        Schema::create('leg_natures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_statement_id')
                ->constrained('financial_statements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('display_label');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leg_natures');
    }
};
