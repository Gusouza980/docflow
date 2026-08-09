<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_client_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'client_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_client_service');
    }
};
