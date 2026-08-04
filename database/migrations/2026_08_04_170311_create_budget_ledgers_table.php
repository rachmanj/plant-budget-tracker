<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained()->cascadeOnDelete();
            $table->enum('entry_type', ['allocation', 'commitment', 'actual', 'carry_forward', 'reversal', 'overbudget']);
            $table->decimal('amount', 18, 2);
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('memo')->nullable();
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['budget_allocation_id', 'entry_type']);
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_ledgers');
    }
};
