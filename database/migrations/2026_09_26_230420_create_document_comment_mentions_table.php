<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_comment_id')->constrained('document_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['document_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comment_mentions');
    }
};
