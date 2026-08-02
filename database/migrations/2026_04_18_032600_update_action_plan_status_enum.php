<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE action_plans
            MODIFY COLUMN status ENUM(
                'open',
                'completed',
                'need_further_review',
                'submitted',
                'closed',
                'in_progress',
                'done',
                'verified'
            ) NOT NULL DEFAULT 'need_further_review'
        ");
    }

    public function down(): void
    {
        //
    }
};