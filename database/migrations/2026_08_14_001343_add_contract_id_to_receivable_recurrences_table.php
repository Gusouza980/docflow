<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_recurrences', function (Blueprint $table) {
            $table->foreignId('contract_id')
                ->nullable()
                ->after('client_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique('contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_recurrences', function (Blueprint $table) {
            $table->dropUnique(['contract_id']);
            $table->dropConstrainedForeignId('contract_id');
        });
    }
};
