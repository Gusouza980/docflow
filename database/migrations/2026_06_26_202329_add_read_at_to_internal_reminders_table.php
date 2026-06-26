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
        Schema::table('internal_reminders', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->index(['user_id', 'read_at'], 'internal_reminders_user_read_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_reminders', function (Blueprint $table): void {
            $table->dropIndex('internal_reminders_user_read_idx');
            $table->dropColumn('read_at');
        });
    }
};
