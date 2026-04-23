<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE action_plans 
            MODIFY status ENUM(
                'open',
                'need_review',
                'completed',
                'draft',
                'submitted',
                'approved',
                'in_progress',
                'done',
                'verified'
            ) DEFAULT 'open'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
