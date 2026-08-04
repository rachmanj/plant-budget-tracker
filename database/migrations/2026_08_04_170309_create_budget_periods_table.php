<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_periods', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 20);
            $table->string('project_name_cache');
            $table->date('period_month');
            $table->enum('status', ['draft', 'open', 'locked', 'closed'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['project_code', 'period_month']);
            $table->index(['status', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
    }
};
