<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_request_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['delay', 'indent', 'constraint', 'general']);
            $table->text('body');
            $table->foreignId('author_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_comments');
    }
};
