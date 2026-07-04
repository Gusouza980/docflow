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
        Schema::create('receivable_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['organization_id', 'receivable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivable_reminders');
    }
};
