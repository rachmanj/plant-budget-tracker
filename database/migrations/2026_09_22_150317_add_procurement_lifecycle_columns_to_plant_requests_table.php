<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plant_requests', function (Blueprint $table) {
            $table->string('sap_po_id', 255)->nullable()->after('sap_pr_no');
            $table->string('sap_grpo_no', 50)->nullable()->after('sap_po_id');
            $table->timestamp('received_at')->nullable()->after('sap_grpo_no');
            $table->unsignedBigInteger('received_by')->nullable()->after('received_at');
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->index('sap_pr_no');
        });
    }

    public function down(): void
    {
        Schema::table('plant_requests', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropIndex(['sap_pr_no']);
            $table->dropColumn(['sap_po_id', 'sap_grpo_no', 'received_at', 'received_by']);
        });
    }
};
