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
        Schema::create('finding_sequences', function (Blueprint $table) {

            $table->id();

            $table->string('company_code');
            $table->integer('year');
            $table->integer('last_number')->default(0);

            $table->timestamps();

            $table->unique([
                'company_code',
                'year'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finding_sequences');
    }
};
