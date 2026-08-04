<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overbudget_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->unique();
            $table->foreignId('budget_allocation_id')->constrained();
            $table->foreignId('plant_request_id')->nullable()->constrained();
            $table->decimal('requested_amount', 18, 2);
            $table->decimal('over_pct', 5, 2);
            $table->enum('status', ['pending_fin_dir', 'pending_ops_dir', 'approved', 'rejected'])->default('pending_fin_dir');
            $table->text('justification');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'budget_allocation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overbudget_requests');
    }
};
