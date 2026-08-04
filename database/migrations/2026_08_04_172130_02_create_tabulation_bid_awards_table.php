<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabulation_bid_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tabulation_bid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tabulation_bid_vendor_id')->constrained();
            $table->text('justification')->nullable();
            $table->foreignId('awarded_by')->constrained('users');
            $table->timestamp('awarded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabulation_bid_awards');
    }
};
