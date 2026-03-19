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

            $table->enum('status',[
                'open',
                'need_review',
                'completed'
            ])->default('open');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_plans');
    }
};