<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->json('monthly_document_items')->nullable()->after('default_billing_interval');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('client_service_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->date('billing_period')->nullable()->after('due_at');
            $table->unique(['client_service_id', 'billing_period'], 'document_requests_service_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropUnique('document_requests_service_period_unique');
            $table->dropConstrainedForeignId('client_service_id');
            $table->dropColumn('billing_period');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('monthly_document_items');
        });
    }
};
