<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('doc_num')->index();
            $table->unsignedBigInteger('sap_doc_entry')->nullable();
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('required_role')->nullable();
            $table->string('approver_name')->nullable();
            $table->string('decision');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->string('legacy_source')->default('proc_app');
            $table->timestamps();

            $table->index('legacy_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_purchase_order_approvals');
    }
};
