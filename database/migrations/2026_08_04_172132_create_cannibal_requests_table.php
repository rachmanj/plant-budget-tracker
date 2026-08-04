<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cannibal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->unique();
            $table->unsignedBigInteger('source_equipment_id');
            $table->unsignedBigInteger('target_equipment_id');
            $table->foreignId('dmbd_entry_id')->constrained('dmbd_entries');
            $table->enum('status', [
                'pending_plant_mgr', 'pending_aml_mgr', 'pending_ops_dir', 'pending_presdir', 'approved', 'rejected',
            ])->default('pending_plant_mgr');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'source_equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cannibal_requests');
    }
};
