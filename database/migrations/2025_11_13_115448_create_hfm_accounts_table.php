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
        Schema::create('hfm_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_category_id')
                ->constrained('account_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('display_label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['account_category_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hfm_accounts');
    }
};
