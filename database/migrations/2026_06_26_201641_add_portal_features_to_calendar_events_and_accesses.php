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
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->boolean('requires_portal_confirmation')->default(false)->after('status');
            $table->string('portal_confirmation_status')->nullable()->after('requires_portal_confirmation');
            $table->text('portal_confirmation_notes')->nullable()->after('portal_confirmation_status');
            $table->timestamp('portal_confirmed_at')->nullable()->after('portal_confirmation_notes');
            $table->foreignId('portal_confirmed_by_access_id')->nullable()->after('portal_confirmed_at')->constrained('client_portal_accesses')->nullOnDelete();
            $table->timestamp('confirmation_deadline_at')->nullable()->after('portal_confirmed_by_access_id');

            $table->index(['organization_id', 'requires_portal_confirmation'], 'calendar_events_portal_confirm_idx');
        });

        Schema::table('client_portal_accesses', function (Blueprint $table): void {
            $table->timestamp('messages_last_read_at')->nullable()->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropForeign(['portal_confirmed_by_access_id']);
            $table->dropIndex('calendar_events_portal_confirm_idx');
            $table->dropColumn([
                'requires_portal_confirmation',
                'portal_confirmation_status',
                'portal_confirmation_notes',
                'portal_confirmed_at',
                'portal_confirmed_by_access_id',
                'confirmation_deadline_at',
            ]);
        });

        Schema::table('client_portal_accesses', function (Blueprint $table): void {
            $table->dropColumn('messages_last_read_at');
        });
    }
};
