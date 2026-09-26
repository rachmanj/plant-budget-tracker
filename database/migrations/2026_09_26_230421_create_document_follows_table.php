<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_follows', function (Blueprint $table) {
            $table->id();
            $table->string('followable_type');
            $table->unsignedBigInteger('followable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['followable_type', 'followable_id', 'user_id']);
            $table->index(['followable_type', 'followable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_follows');
    }
};
