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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('primary_responsible_member_id')->nullable()->constrained('organization_members')->nullOnDelete();
            $table->string('type')->index();
            $table->string('display_name');
            $table->string('document_number')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('risk_level')->default('low')->index();
            $table->unsignedInteger('potential_revenue_cents')->nullable();
            $table->string('origin')->nullable();
            $table->string('access_policy')->default('all_members')->index();
            $table->text('internal_notes')->nullable();
            $table->date('entered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'document_number']);
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'display_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
