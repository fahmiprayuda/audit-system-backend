<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {

            $table->id();

            $table->string('finding_code')->unique();

            $table->foreignId('audit_project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            $table->text('description')->nullable();

            $table->enum('risk_rating',[
                'Extreme',
                'Major',
                'Moderate'
            ]);

            $table->enum('risk_category',[
                'Significant',
                'Moderate'
            ]);

            $table->date('due_date')->nullable();

            $table->enum('status',[
                'open',
                'need_review',
                'closed'
            ])->default('open');

            $table->foreignId('created_by')
                ->constrained('users');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};