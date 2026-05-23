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

                    $table->date('start_date')->nullable();
                    $table->date('target_date')->nullable();

                    $table->enum('status',[
                        'draft', //open
                        'submitted', //NFR
                        'need_revision', //NFR
                        'approved' //closed
                    ])->default('draft');

                    // Audit trail

                    $table->timestamp('submitted_at')->nullable();
                    $table->timestamp('approved_at')->nullable();

                    $table->foreignId('submitted_by')
                        ->nullable()
                        ->constrained('users');

                    $table->foreignId('approved_by')
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