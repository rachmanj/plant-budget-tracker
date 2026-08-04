<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dmbd_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            $table->string('unit_code_cache');
            $table->date('report_date');
            $table->enum('operational_status', ['rfu', 'standby', 'breakdown']);
            $table->text('breakdown_note')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->boolean('synced_to_arkfleet')->default(false);
            $table->timestamps();

            $table->unique(['equipment_id', 'report_date']);
            $table->index(['report_date', 'operational_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dmbd_entries');
    }
};
