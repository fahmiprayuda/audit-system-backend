<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add soft deletes to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // 2. Fix action_plans.submitted_by foreign key to nullOnDelete
        Schema::table('action_plans', function (Blueprint $table) {
            // Drop existing foreign key
            $table->dropForeign(['submitted_by']);

            // Re-add with nullOnDelete
            $table->foreign('submitted_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->foreign('submitted_by')
                ->references('id')
                ->on('users');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
