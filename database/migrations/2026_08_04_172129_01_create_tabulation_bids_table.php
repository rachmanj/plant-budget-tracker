<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabulation_bids', function (Blueprint $table) {
            $table->id();
            $table->string('bid_no', 30)->unique();
            $table->string('sap_pr_id');
            $table->enum('status', ['draft', 'pending_proc_mgr', 'forwarded_admin', 'po_created', 'closed'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->string('sap_po_id')->nullable();
            $table->boolean('sap_sync_failed')->default(false);
            $table->timestamps();

            $table->index('sap_pr_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabulation_bids');
    }
};
