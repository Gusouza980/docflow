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
        Schema::create('client_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('client_portal_access_id')->nullable();
            $table->string('channel', 32)->default('email');
            $table->string('direction', 32);
            $table->string('status', 32)->default('registered');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'client_id', 'created_at']);
            $table->index(['organization_id', 'channel', 'direction', 'status']);
            $table->index(['ticket_id', 'client_portal_access_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_messages');
    }
};
