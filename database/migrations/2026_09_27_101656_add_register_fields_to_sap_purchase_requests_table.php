<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sap_purchase_requests', function (Blueprint $table) {
            $table->date('required_date')->nullable()->after('mr_no');
            $table->text('remarks')->nullable()->after('required_date');
            $table->string('pr_status', 32)->nullable()->after('remarks');
            $table->string('closed_status', 32)->nullable()->after('pr_status');
            $table->string('pr_rev_no')->nullable()->after('closed_status');
            $table->string('unit_no')->nullable()->after('pr_rev_no');
            $table->decimal('hours_meter', 18, 2)->nullable()->after('unit_no');
            $table->unsignedInteger('line_count')->default(0)->after('hours_meter');
            $table->decimal('total_amount', 18, 2)->default(0)->after('line_count');

            $table->index('pr_status');
            $table->index('required_date');
            $table->index('create_date');
        });

        Schema::table('sap_purchase_request_lines', function (Blueprint $table) {
            $table->decimal('line_amount', 18, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sap_purchase_request_lines', function (Blueprint $table) {
            $table->dropColumn('line_amount');
        });

        Schema::table('sap_purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['pr_status']);
            $table->dropIndex(['required_date']);
            $table->dropIndex(['create_date']);
            $table->dropColumn([
                'required_date',
                'remarks',
                'pr_status',
                'closed_status',
                'pr_rev_no',
                'unit_no',
                'hours_meter',
                'line_count',
                'total_amount',
            ]);
        });
    }
};
