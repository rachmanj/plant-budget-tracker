<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabulation_bid_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tabulation_bid_id')->constrained()->cascadeOnDelete();
            $table->string('vendor_code');
            $table->string('vendor_name');
            $table->decimal('price', 18, 2);
            $table->string('payment_terms')->nullable();
            $table->enum('stock_availability', ['ready', 'indent', 'partial']);
            $table->text('remarks')->nullable();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->timestamps();

            $table->index('tabulation_bid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabulation_bid_vendors');
    }
};
