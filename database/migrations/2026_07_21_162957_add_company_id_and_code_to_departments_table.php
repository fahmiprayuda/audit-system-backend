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
        Schema::table('departments', function (Blueprint $table) {

            $table->foreignId('company_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('code')
                ->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {

            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'company_id',
                'code'
            ]);

        });
    }
};
