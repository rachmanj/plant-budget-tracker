<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_price_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamp('imported_at');
            $table->timestamps();
        });

        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');
            $table->string('vendor_code')->default('');
            $table->string('uom')->nullable();
            $table->decimal('price', 18, 2);
            $table->string('currency', 3)->default('IDR');
            $table->date('effective_date')->nullable();
            $table->string('source');
            $table->text('note')->nullable();
            $table->foreignId('last_import_id')->nullable()->constrained('item_price_imports')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['item_code', 'vendor_code']);
        });

        Schema::create('item_price_histories', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');
            $table->string('vendor_code')->default('');
            $table->decimal('old_price', 18, 2)->nullable();
            $table->decimal('new_price', 18, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('source');
            $table->date('effective_date')->nullable();
            $table->foreignId('import_id')->nullable()->constrained('item_price_imports')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['item_code', 'vendor_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_price_histories');
        Schema::dropIfExists('item_prices');
        Schema::dropIfExists('item_price_imports');
    }
};
