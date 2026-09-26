<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE tabulation_bids MODIFY COLUMN status ENUM(
            'draft',
            'pending_proc_mgr',
            'pending_presdir',
            'forwarded_admin',
            'po_created',
            'closed'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_settings');

        DB::statement("ALTER TABLE tabulation_bids MODIFY COLUMN status ENUM(
            'draft',
            'pending_proc_mgr',
            'forwarded_admin',
            'po_created',
            'closed'
        ) NOT NULL DEFAULT 'draft'");
    }
};
