<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {

            $table->id();

            $table->foreignId('action_plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('verified_by')
                ->constrained('users');

            $table->enum('status',[
                'approved',
                'rejected'
            ]);

            $table->text('note')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};