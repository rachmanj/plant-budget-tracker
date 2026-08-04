<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('components')->nullOnDelete();
            $table->enum('level', ['housing', 'inner', 'critical']);
            $table->unsignedBigInteger('equipment_id');
            $table->string('component_code');
            $table->string('description');
            $table->enum('status', ['installed', 'removed', 'cannibalized', 'scrapped'])->default('installed');
            $table->foreignId('maintained_by')->nullable()->constrained('users');
            $table->boolean('synced_to_arkfleet')->default(false);
            $table->timestamps();

            $table->index(['equipment_id', 'status']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
