<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->nullableMorphs('subject');
            $table->string('dedupe_key');
            $table->string('status');
            $table->json('result')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'dedupe_key']);
            $table->index(['automation_rule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};
