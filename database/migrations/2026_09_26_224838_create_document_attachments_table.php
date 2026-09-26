<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
