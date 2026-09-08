<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects_cache', function (Blueprint $table) {
            if (! Schema::hasColumn('projects_cache', 'location')) {
                $table->string('location')->nullable()->after('project_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects_cache', function (Blueprint $table) {
            if (Schema::hasColumn('projects_cache', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
};
