<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sap_purchase_orders', function (Blueprint $table) {
            $table->string('origin', 8)->default('sap')->after('budget_type');
            $table->foreignId('plant_request_id')
                ->nullable()
                ->after('origin')
                ->constrained('plant_requests')
                ->nullOnDelete();
            $table->index('plant_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('sap_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['plant_request_id']);
            $table->dropColumn(['origin', 'plant_request_id']);
        });
    }
};
