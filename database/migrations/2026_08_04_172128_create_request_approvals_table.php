<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedInteger('step_order');
            $table->string('required_role');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->enum('decision', ['pending', 'approved', 'rejected', 'returned'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
