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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->text('payment_instructions')->nullable()->after('settings');
        });

        Schema::table('receivables', function (Blueprint $table): void {
            $table->timestamp('last_portal_reminder_at')->nullable()->after('notes');
            $table->string('payment_reference')->nullable()->after('last_portal_reminder_at');
            $table->string('payment_url')->nullable()->after('payment_reference');
        });

        Schema::create('scheduler_run_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('command');
            $table->timestamp('ran_at');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('result', 32);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['command', 'ran_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler_run_logs');

        Schema::table('receivables', function (Blueprint $table): void {
            $table->dropColumn(['last_portal_reminder_at', 'payment_reference', 'payment_url']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('payment_instructions');
        });
    }
};
