<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_logs', function (Blueprint $table) {
            $table->unsignedInteger('estimated_minutes_saved')->nullable()->after('result');
            $table->index(['organization_id', 'status', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::table('automation_logs', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'ran_at']);
            $table->dropColumn('estimated_minutes_saved');
        });
    }
};
