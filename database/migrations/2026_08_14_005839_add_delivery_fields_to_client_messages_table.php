<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_messages', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('ticket_id')->constrained('message_batches')->nullOnDelete();
            $table->text('failure_reason')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('client_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn('failure_reason');
        });
    }
};
