<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects_cache', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 20)->unique();
            $table->string('project_name');
            $table->boolean('is_active')->default(true);
            $table->boolean('selectable_only')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_cache');
    }
};
