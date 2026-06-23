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
        Schema::create('action_plan_extensions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('action_plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('old_due_date');

            $table->date('new_due_date');

            $table->enum('status_after_extension', [
                'open',
                'need_further_review',
                'closed'
            ]);

            $table->text('reason');

            $table->foreignId('extended_by')
                ->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_plan_extensions');
    }
};
