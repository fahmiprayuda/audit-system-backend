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
        Schema::create('action_plan_comments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('action_plan_id')
            ->constrained()
            ->cascadeOnDelete();

            $table->string('role'); // auditor / auditee
            
            $table->text('message');
            
            $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

            
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_plan_comments');
    }
};
