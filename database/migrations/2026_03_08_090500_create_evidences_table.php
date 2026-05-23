<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {

            $table->id();

            $table->foreignId('action_plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('file_path');

            $table->string('file_name')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};