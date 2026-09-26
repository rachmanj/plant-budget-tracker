<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sap_doc_entry')->unique();
            $table->unsignedBigInteger('doc_num')->nullable();
            $table->date('doc_date')->nullable();
            $table->dateTime('create_date')->nullable();
            $table->dateTime('delivery_date')->nullable();
            $table->dateTime('po_eta')->nullable();
            $table->string('pr_no')->nullable();
            $table->string('vendor_code')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('project_code')->nullable();
            $table->string('dept_code')->nullable();
            $table->string('dept_name')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('total_amount', 18, 2)->nullable();
            $table->decimal('vat_amount', 18, 2)->nullable();
            $table->decimal('disc_amount', 18, 2)->nullable();
            $table->string('delivery_status', 1)->nullable();
            $table->string('budget_type')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('doc_num');
            $table->index('pr_no');
            $table->index('project_code');
            $table->index('dept_code');
            $table->index('doc_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_purchase_orders');
    }
};
