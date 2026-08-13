<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger');
            $table->string('preset_key')->nullable();
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
