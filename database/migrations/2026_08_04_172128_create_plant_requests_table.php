<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->unique();
            $table->foreignId('budget_allocation_id')->constrained();
            $table->unsignedBigInteger('equipment_id');
            $table->string('unit_code_cache');
            $table->foreignId('dmbd_entry_id')->nullable()->constrained('dmbd_entries');
            $table->unsignedBigInteger('sap_mr_id');
            $table->string('sap_pr_no')->nullable();
            $table->enum('status', [
                'draft', 'pending_pm', 'pending_plant_mgr', 'approved',
                'pr_created', 'po_created', 'received', 'cancelled', 'rejected',
            ])->default('draft');
            $table->decimal('estimated_total', 18, 2)->default(0);
            $table->decimal('budget_utilization_pct', 5, 2)->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'budget_allocation_id']);
            $table->index('sap_mr_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_requests');
    }
};
