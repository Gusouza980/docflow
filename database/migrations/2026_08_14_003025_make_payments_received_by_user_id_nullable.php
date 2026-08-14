<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['received_by_user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('received_by_user_id')->nullable()->change();
            $table->foreign('received_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['received_by_user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('received_by_user_id')->nullable(false)->change();
            $table->foreign('received_by_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
