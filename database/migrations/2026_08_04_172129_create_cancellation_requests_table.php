<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_request_id')->constrained();
            $table->string('sap_po_id')->nullable();
            $table->enum('po_stage', ['created', 'approved', 'sent'])->nullable();
            $table->enum('initiated_by', ['plant', 'procurement']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('budget_reversal_amount', 18, 2)->default(0);
            $table->text('reason');
            $table->foreignId('agreed_by')->nullable()->constrained('users');
            $table->timestamp('agreed_at')->nullable();
            $table->timestamps();

            $table->index(['plant_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_requests');
    }
};
