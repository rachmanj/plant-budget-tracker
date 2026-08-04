<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cannibal_request_component', function (Blueprint $table) {
            $table->foreignId('cannibal_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->primary(['cannibal_request_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cannibal_request_component');
    }
};
