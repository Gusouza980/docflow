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
        Schema::create('calendar_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['organization_id', 'calendar_event_id'], 'event_participants_org_event_idx');
            $table->index(['organization_id', 'organization_member_id'], 'event_participants_org_member_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_participants');
    }
};
