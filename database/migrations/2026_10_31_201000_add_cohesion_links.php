<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('contract_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('document_request_id')->nullable()->after('contract_id')->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->after('document_request_id')->constrained()->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('receivable_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('client_service_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('client_messages', function (Blueprint $table): void {
            $table->foreignId('task_id')->nullable()->after('ticket_id')->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->after('task_id')->constrained()->nullOnDelete();
            $table->foreignId('receivable_id')->nullable()->after('document_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receivable_id');
            $table->dropConstrainedForeignId('document_id');
            $table->dropConstrainedForeignId('task_id');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_service_id');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receivable_id');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ticket_id');
            $table->dropConstrainedForeignId('document_request_id');
            $table->dropConstrainedForeignId('contract_id');
        });
    }
};
