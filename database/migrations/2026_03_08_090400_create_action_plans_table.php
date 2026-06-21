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

                        $table->text('corrective_action');

                        $table->date('due_date')->nullable();

                        $table->enum('status', [
                            'need_further_review',
                            'open',
                            'closed'
                        ])->default('need_further_review');

                        $table->json('flags')->nullable();

                        // Audit trail

                        $table->timestamp('submitted_at')->nullable();
                        $table->timestamp('closed_at')->nullable();

                        $table->foreignId('submitted_by')
                            ->nullable()
                            ->constrained('users');

                        $table->foreignId('closed_by')
                            ->nullable()
                            ->constrained('users')
                            ->nullOnDelete();

                        $table->timestamps();
            });
        }

    public function down(): void
    {
        Schema::dropIfExists('action_plans');
    }
};