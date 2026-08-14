<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('asaas');
            $table->string('provider_payment_id');
            $table->string('billing_type');
            $table->string('status')->default('pending');
            $table->string('invoice_url')->nullable();
            $table->text('pix_payload')->nullable();
            $table->longText('pix_encoded_image')->nullable();
            $table->string('bank_slip_url')->nullable();
            $table->string('identification_field')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('provider_payment_id');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_charges');
    }
};
