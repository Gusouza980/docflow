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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_member_id')->nullable()->constrained('organization_members')->nullOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('client_messages')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('new');
            $table->string('priority', 32)->default('normal');
            $table->boolean('visible_to_client')->default(true);
            $table->date('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'client_id', 'status']);
            $table->index(['organization_id', 'assigned_to_member_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
