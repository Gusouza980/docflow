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
        Schema::table('report_schedules', function (Blueprint $table): void {
            $table->text('last_error')->nullable()->after('last_run_at');
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_error');
        });

        Schema::table('generated_reports', function (Blueprint $table): void {
            $table->foreignId('report_schedule_id')->nullable()->after('generated_by_user_id')->constrained()->nullOnDelete();
            $table->date('period_start')->nullable()->after('filters');
            $table->date('period_end')->nullable()->after('period_start');

            $table->unique(['report_schedule_id', 'period_start', 'period_end'], 'generated_reports_schedule_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_reports', function (Blueprint $table): void {
            $table->dropUnique('generated_reports_schedule_period_unique');
            $table->dropConstrainedForeignId('report_schedule_id');
            $table->dropColumn(['period_start', 'period_end']);
        });

        Schema::table('report_schedules', function (Blueprint $table): void {
            $table->dropColumn(['last_error', 'consecutive_failures']);
        });
    }
};
