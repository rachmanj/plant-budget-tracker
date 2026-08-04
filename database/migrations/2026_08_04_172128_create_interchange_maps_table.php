<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interchange_maps', function (Blueprint $table) {
            $table->id();
            $table->string('genuine_part_number');
            $table->string('oem_part_number');
            $table->string('material_name');
            $table->boolean('sap_synced')->default(false);
            $table->string('sap_sync_ref')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('technical_signoff_by')->nullable()->constrained('users');
            $table->timestamp('technical_signoff_at')->nullable();
            $table->timestamps();

            $table->unique(['genuine_part_number', 'oem_part_number']);
            $table->index('sap_synced');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interchange_maps');
    }
};
