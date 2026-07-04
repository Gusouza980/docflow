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
        Schema::table('receivables', function (Blueprint $table): void {
            $table->foreignId('receivable_recurrence_id')->nullable()->after('created_by_user_id')->constrained()->nullOnDelete();
            $table->date('billing_period')->nullable()->after('competence_date');
            $table->foreignId('renegotiated_from_receivable_id')->nullable()->after('cancellation_reason')->constrained('receivables')->nullOnDelete();
            $table->foreignId('renegotiated_to_receivable_id')->nullable()->after('renegotiated_from_receivable_id')->constrained('receivables')->nullOnDelete();
            $table->text('renegotiation_reason')->nullable()->after('renegotiated_to_receivable_id');
            $table->timestamp('renegotiated_at')->nullable()->after('renegotiation_reason');

            $table->unique(['receivable_recurrence_id', 'billing_period'], 'receivables_recurrence_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receivables', function (Blueprint $table): void {
            $table->dropUnique('receivables_recurrence_period_unique');
            $table->dropConstrainedForeignId('receivable_recurrence_id');
            $table->dropConstrainedForeignId('renegotiated_from_receivable_id');
            $table->dropConstrainedForeignId('renegotiated_to_receivable_id');
            $table->dropColumn([
                'billing_period',
                'renegotiation_reason',
                'renegotiated_at',
            ]);
        });
    }
};
