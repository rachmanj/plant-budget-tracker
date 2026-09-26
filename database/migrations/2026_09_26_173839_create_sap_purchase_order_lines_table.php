<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sap_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sap_doc_entry');
            $table->integer('line_num');
            $table->integer('vis_order');
            $table->string('item_code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('qty', 18, 2)->nullable();
            $table->string('uom')->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('item_amount', 18, 2)->nullable();
            $table->string('project_code')->nullable();
            $table->string('unit_no')->nullable();
            $table->text('remark1')->nullable();
            $table->text('remark2')->nullable();
            $table->timestamps();

            $table->unique(['sap_doc_entry', 'line_num', 'vis_order'], 'sap_po_lines_doc_line_vis_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_purchase_order_lines');
    }
};
