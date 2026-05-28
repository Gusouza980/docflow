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
        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_member_id')->constrained('organization_members')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('general');
            $table->string('urgency')->default('normal');
            $table->string('status')->default('pending');
            $table->date('due_at');
            $table->boolean('requires_review')->default(false);
            $table->timestamp('review_requested_at')->nullable();
            $table->timestamp('review_approved_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'client_id']);
            $table->index(['organization_id', 'assigned_to_member_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'urgency']);
            $table->index(['organization_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deadlines');
    }
};
