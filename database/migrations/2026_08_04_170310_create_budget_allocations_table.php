<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->string('unit_code_cache')->nullable();
            $table->enum('plant_type_cache', ['DIGGER', 'HAULER', 'SUPPORT'])->nullable();
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->decimal('tolerance_pct', 5, 2)->default(10.00);
            $table->decimal('carry_forward_in', 18, 2)->default(0);
            $table->decimal('committed_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->boolean('is_editable')->default(false);
            $table->timestamps();

            $table->index(['budget_period_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
    }
};
