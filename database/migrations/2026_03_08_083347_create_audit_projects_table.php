<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_projects', function (Blueprint $table) {

            $table->id();

            $table->string('project_code')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('project_name');

            $table->date('release_date')->nullable();

            $table->enum('status', [
                'open',
                'in_progress',
                'closed'
            ])->default('open');

            $table->foreignId('created_by')
                ->constrained('users');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_projects');
    }
};