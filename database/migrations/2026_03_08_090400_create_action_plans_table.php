<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_plans', function (Blueprint $table) {

            $table->id();

            $table->foreignId('finding_department_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('target_date')->nullable();

            // 🔥 STATUS BARU (FIXED)
            $table->enum('status',[
                'draft',
                'submitted',
                'need_revision',
                'approved',
                'in_progress',
                'done',
                'verified'
            ])->default('draft');

            // 🔥 AUDIT FLOW
            $table->text('auditee_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            // 🔥 Traceable
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');

            // 🔥 WAJIB
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_plans');
    }
};