<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sap_purchase_orders', function (Blueprint $table) {
            $table->string('legacy_source')->nullable()->after('plant_request_id');
            $table->string('legacy_doc_num')->nullable()->after('legacy_source');

            $table->index('legacy_source');
        });

        Schema::table('sap_purchase_requests', function (Blueprint $table) {
            $table->string('legacy_source')->nullable()->after('project_code');
            $table->string('legacy_doc_num')->nullable()->after('legacy_source');

            $table->index('legacy_source');
        });

        Schema::table('document_attachments', function (Blueprint $table) {
            $table->string('legacy_source')->nullable()->after('uploaded_by');
            $table->unsignedBigInteger('legacy_id')->nullable()->after('legacy_source');
            $table->boolean('file_unavailable')->default(false)->after('legacy_id');

            $table->index('legacy_source');
        });
    }

    public function down(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            $table->dropIndex(['legacy_source']);
            $table->dropColumn(['legacy_source', 'legacy_id', 'file_unavailable']);
        });

        Schema::table('sap_purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['legacy_source']);
            $table->dropColumn(['legacy_source', 'legacy_doc_num']);
        });

        Schema::table('sap_purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['legacy_source']);
            $table->dropColumn(['legacy_source', 'legacy_doc_num']);
        });
    }
};
