<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portal_accesses', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('password_set_at')->nullable()->after('password');
            $table->timestamp('onboarding_completed_at')->nullable()->after('password_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_portal_accesses', function (Blueprint $table): void {
            $table->dropColumn(['password', 'password_set_at', 'onboarding_completed_at']);
        });
    }
};
