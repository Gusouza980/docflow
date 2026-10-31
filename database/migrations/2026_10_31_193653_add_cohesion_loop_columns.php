<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('creates_receivable_recurrence')->default(false)->after('auto_renew');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });

        Schema::table('message_batches', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->after('filter');
            $table->unique(['organization_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('message_batches', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'fingerprint']);
            $table->dropColumn('fingerprint');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('creates_receivable_recurrence');
        });
    }
};
