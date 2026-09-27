<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sap_purchase_requests', function (Blueprint $table) {
            $table->string('pr_type', 32)->nullable()->change();
        });

        Schema::table('sap_purchase_orders', function (Blueprint $table) {
            $table->string('delivery_status', 32)->nullable()->change();
            $table->string('currency', 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sap_purchase_orders', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->change();
            $table->string('delivery_status', 1)->nullable()->change();
        });

        Schema::table('sap_purchase_requests', function (Blueprint $table) {
            $table->string('pr_type', 1)->nullable()->change();
        });
    }
};
