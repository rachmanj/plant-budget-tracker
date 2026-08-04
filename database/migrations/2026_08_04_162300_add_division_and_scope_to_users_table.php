<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no')->nullable()->unique()->after('email');
            $table->enum('division', ['plant', 'aml', 'procurement', 'finance', 'directorate', 'it'])->nullable()->after('employee_no');
            $table->string('project_code_scope', 20)->nullable()->after('division');
            $table->boolean('is_active')->default(true)->after('project_code_scope');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_no', 'division', 'project_code_scope', 'is_active']);
        });
    }
};
