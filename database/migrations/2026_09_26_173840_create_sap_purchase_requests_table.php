<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sap_doc_entry')->unique();
            $table->unsignedBigInteger('doc_num')->nullable();
            $table->date('doc_date')->nullable();
            $table->dateTime('create_date')->nullable();
            $table->string('pr_type', 1)->nullable();
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->string('requester')->nullable();
            $table->string('mr_no')->nullable();
            $table->string('project_code')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('doc_num');
            $table->index('mr_no');
            $table->index('project_code');
            $table->index('department_code');
            $table->index('doc_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_purchase_requests');
    }
};
