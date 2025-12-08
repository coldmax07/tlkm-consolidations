<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->unique(
                ['description', 'financial_statement_id', 'sender_company_id', 'receiver_company_id'],
                'templates_description_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropUnique('templates_description_scope_unique');
        });
    }
};
