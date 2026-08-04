<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation');
            $table->string('correlation_key')->unique();
            $table->string('ref_type');
            $table->unsignedBigInteger('ref_id');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['ref_type', 'ref_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_sync_logs');
    }
};
